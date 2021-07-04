<?php

declare(strict_types = 1);

namespace StasPiv\FeatureFlags;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class FeatureChecker
{
    private const TOKEN_HEADER_NAME = 'PRIVATE-TOKEN';
    private const FEATURE_FLAGS_URL = '%sprojects/%s/feature_flags/%s';

    /**
     * @var array[]
     */
    private $featureMap = [];

    /**
     * @var string[]
     */
    private $nonExistentFeatures = [];

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var int
     */
    private $projectId;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $applicationEnv;

    public function __construct(string $baseUrl, int $projectId, string $token, string $applicationEnv)
    {
        $this->baseUrl = $baseUrl;
        $this->projectId = $projectId;
        $this->token = $token;
        $this->applicationEnv = $applicationEnv;
    }

    public function isFeatureEnabled(string $featureName): bool
    {
        return $this->checkFeatureState($featureName, FeatureStatusInterface::STATUS_ENABLED);
    }

    public function getStatus(string $featureName): string
    {
        if (in_array($featureName, $this->nonExistentFeatures)) {
            return FeatureStatusInterface::STATUS_NOT_AVAILABLE;
        }

        try {
            $content = $this->requestFeatureFromGitlab($featureName);
        } catch (GuzzleException $exception) {
            $this->nonExistentFeatures[] = $featureName;

            return FeatureStatusInterface::STATUS_NOT_AVAILABLE;
        }

        if (is_null($content)) {
            return FeatureStatusInterface::STATUS_NOT_AVAILABLE;
        }

        if (!$content['active']) {
            return FeatureStatusInterface::STATUS_AVAILABLE;
        }

        if (in_array($this->applicationEnv, $this->getScopes($content['strategies'][0]['scopes']))) {
            return FeatureStatusInterface::STATUS_ENABLED;
        }

        return FeatureStatusInterface::STATUS_AVAILABLE;
    }

    private function checkFeatureState(string $featureName, string $requestedFeatureState): bool
    {
        return $this->getStatus($featureName) === $requestedFeatureState;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function requestFeatureFromGitlab(string $featureName): array
    {
        if (isset($this->featureMap[$featureName])) {
            return $this->featureMap[$featureName];
        }

        $response = (new Client())->get(
            sprintf(
                static::FEATURE_FLAGS_URL,
                $this->baseUrl,
                $this->projectId,
                $featureName
            ),
            [
                'headers' => [
                    static::TOKEN_HEADER_NAME => $this->token,
                ]
            ]
        );

        return $this->featureMap[$featureName] = json_decode($response->getBody()->getContents(), true);
    }

    private function getScopes(array $scopes): array
    {
        return array_map(
            function (array $scope) {
                return $scope['environment_scope'];
            },
            $scopes
        );
    }
}

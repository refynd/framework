<?php

namespace Refynd\Api;

use Refynd\Api\Contracts\ApiVersionInterface;

/**
 * ApiVersion - Represents an API version
 *
 * Manages version-specific behavior, deprecation warnings,
 * and feature availability in the API system.
 */
class ApiVersion implements ApiVersionInterface
{
    protected string $version;
    protected array $features = [];
    protected ?array $deprecation = null;
    protected array $changes = [];
    protected \DateTime $releaseDate;

    public function __construct(
        string $version,
        array $features = [],
        ?array $deprecation = null,
        array $changes = []
    ) {
        $this->version = $version;
        $this->features = $features;
        $this->deprecation = $deprecation;
        $this->changes = $changes;
        $this->releaseDate = new \DateTime();
    }

    /**
     * Get the version number
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Check if this version supports a feature
     */
    public function supports(string $feature): bool
    {
        return in_array($feature, $this->features);
    }

    /**
     * Get deprecation information
     */
    public function getDeprecation(): ?array
    {
        return $this->deprecation;
    }

    /**
     * Check if version is deprecated
     */
    public function isDeprecated(): bool
    {
        return $this->deprecation !== null;
    }

    /**
     * Add a feature to this version
     */
    public function addFeature(string $feature): self
    {
        if (!in_array($feature, $this->features)) {
            $this->features[] = $feature;
        }
        return $this;
    }

    /**
     * Remove a feature from this version
     */
    public function removeFeature(string $feature): self
    {
        $this->features = array_filter($this->features, fn ($f) => $f !== $feature);
        return $this;
    }

    /**
     * Set deprecation information
     */
    public function deprecate(string $message, ?\DateTime $sunset = null, ?string $replacement = null): self
    {
        $this->deprecation = ['message' => $message,
            'sunset_date' => $sunset?->format('Y-m-d'),
            'replacement_version' => $replacement,
            'deprecated_since' => (new \DateTime())->format('Y-m-d'),];
        return $this;
    }

    /**
     * Get version changes/changelog
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Add a change to this version
     */
    public function addChange(string $type, string $description): self
    {
        $this->changes[] = ['type' => $type,
            'description' => $description,
            'date' => (new \DateTime())->format('Y-m-d'),];
        return $this;
    }

    /**
     * Get release date
     */
    public function getReleaseDate(): \DateTime
    {
        return $this->releaseDate;
    }

    /**
     * Set release date
     */
    public function setReleaseDate(\DateTime $date): self
    {
        $this->releaseDate = $date;
        return $this;
    }

    /**
     * Compare versions
     */
    public function isNewerThan(ApiVersion $other): bool
    {
        return version_compare($this->version, $other->getVersion(), '>');
    }

    /**
     * Compare versions
     */
    public function isOlderThan(ApiVersion $other): bool
    {
        return version_compare($this->version, $other->getVersion(), '<');
    }

    /**
     * Check if versions are equal
     */
    public function equals(ApiVersion $other): bool
    {
        return $this->version === $other->getVersion();
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return ['version' => $this->version,
            'features' => $this->features,
            'deprecation' => $this->deprecation,
            'changes' => $this->changes,
            'release_date' => $this->releaseDate->format('Y-m-d'),];
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->version;
    }
}

/**
 * ApiVersionManager - Manages API versions
 *
 * Handles version negotiation, feature detection,
 * and deprecation management across API versions.
 */
class ApiVersionManager
{
    protected array $versions = [];
    protected ?ApiVersion $defaultVersion = null;
    protected array $versionRoutes = [];

    /**
     * Register an API version
     */
    public function register(ApiVersion $version): self
    {
        $this->versions[$version->getVersion()] = $version;

        // Set as default if none exists
        if ($this->defaultVersion === null) {
            $this->defaultVersion = $version;
        }

        return $this;
    }

    /**
     * Get version by string
     */
    public function get(string $version): ?ApiVersion
    {
        return $this->versions[$version] ?? null;
    }

    /**
     * Get all versions
     */
    public function all(): array
    {
        return $this->versions;
    }

    /**
     * Get latest version
     */
    public function latest(): ?ApiVersion
    {
        if (empty($this->versions)) {
            return null;
        }

        $latest = null;
        foreach ($this->versions as $version) {
            if ($latest === null || $version->isNewerThan($latest)) {
                $latest = $version;
            }
        }

        return $latest;
    }

    /**
     * Get default version
     */
    public function default(): ?ApiVersion
    {
        return $this->defaultVersion;
    }

    /**
     * Set default version
     */
    public function setDefault(string $version): self
    {
        if (isset($this->versions[$version])) {
            $this->defaultVersion = $this->versions[$version];
        }
        return $this;
    }

    /**
     * Negotiate version from request
     */
    public function negotiate(string $accept, string $userAgent = ''): ?ApiVersion
    {
        // Try to extract version from Accept header
        // e.g., Accept: application/vnd.api+json;version = 1.0
        if (preg_match('/version = ([0-9.]+)/', $accept, $matches)) {
            $requestedVersion = $matches[1];
            if (isset($this->versions[$requestedVersion])) {
                return $this->versions[$requestedVersion];
            }
        }

        // Try to extract from User-Agent
        if (preg_match('/API\/([0-9.]+)/', $userAgent, $matches)) {
            $requestedVersion = $matches[1];
            if (isset($this->versions[$requestedVersion])) {
                return $this->versions[$requestedVersion];
            }
        }

        // Return default version
        return $this->defaultVersion;
    }

    /**
     * Get supported versions
     */
    public function supported(): array
    {
        return array_filter($this->versions, fn ($version) => !$version->isDeprecated());
    }

    /**
     * Get deprecated versions
     */
    public function deprecated(): array
    {
        return array_filter($this->versions, fn ($version) => $version->isDeprecated());
    }

    /**
     * Check if version exists
     */
    public function exists(string $version): bool
    {
        return isset($this->versions[$version]);
    }

    /**
     * Remove a version
     */
    public function remove(string $version): bool
    {
        if (isset($this->versions[$version])) {
            unset($this->versions[$version]);

            // Reset default if it was removed
            if ($this->defaultVersion && $this->defaultVersion->getVersion() === $version) {
                $this->defaultVersion = $this->latest();
            }

            return true;
        }

        return false;
    }

    /**
     * Create version changelog
     */
    public function changelog(): array
    {
        $changelog = [];

        foreach ($this->versions as $version) {
            $changelog[$version->getVersion()] = ['version' => $version->getVersion(),
                'release_date' => $version->getReleaseDate()->format('Y-m-d'),
                'changes' => $version->getChanges(),
                'features' => $version->supports('*') ? ['All features'] : [],
                'deprecation' => $version->getDeprecation(),];
        }

        // Sort by version descending
        uksort($changelog, fn ($a, $b) => version_compare($b, $a));

        return $changelog;
    }

    /**
     * Get API health status
     */
    public function health(): array
    {
        $total = count($this->versions);
        $deprecated = count($this->deprecated());
        $supported = count($this->supported());

        return ['total_versions' => $total,
            'supported_versions' => $supported,
            'deprecated_versions' => $deprecated,
            'latest_version' => $this->latest()?->getVersion(),
            'default_version' => $this->defaultVersion?->getVersion(),
            'health_score' => $total > 0 ? ($supported / $total) * 100 : 0,];
    }
}

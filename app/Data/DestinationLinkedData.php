<?php

declare(strict_types=1);

namespace App\Data;

final readonly class DestinationLinkedData
{
    /**
     * @param  list<LinkedResource>  $categories
     * @param  list<LinkedResource>  $facilities
     * @param  list<LinkedResource>  $activities
     * @param  list<LinkedResource>  $neighbors
     */
    public function __construct(
        public string $iri,
        public array $categories = [],
        public ?LinkedResource $region = null,
        public array $facilities = [],
        public array $activities = [],
        public array $neighbors = [],
    ) {}

    public function categoryForLabel(string $label): ?LinkedResource
    {
        return $this->findByLabel($this->categories, $label);
    }

    public function facilityForLabel(string $label): ?LinkedResource
    {
        return $this->findByLabel($this->facilities, $label);
    }

    public function activityForLabel(string $label): ?LinkedResource
    {
        return $this->findByLabel($this->activities, $label);
    }

    /**
     * @param  list<LinkedResource>  $resources
     */
    private function findByLabel(array $resources, string $label): ?LinkedResource
    {
        foreach ($resources as $resource) {
            if ($resource->label === $label) {
                return $resource;
            }
        }

        return null;
    }
}

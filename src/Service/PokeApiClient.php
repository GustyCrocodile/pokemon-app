<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PokeApiClient
{
    private const BASE_URL = 'https://pokeapi.co/api/v2';

    public function __construct(
        private HttpClientInterface $httpClient
    ) {
    }

    public function getTypes(): array
    {
        return $this->httpClient->request(
            'GET',
            self::BASE_URL . '/type'
        )->toArray();
    }

    public function getPokemonList(int $limit = 20, int $offset = 0): array
    {
        return $this->httpClient->request(
            'GET',
            self::BASE_URL . '/pokemon',
            [
                'query' => [
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]
        )->toArray();
    }

    public function getPokemon(string|int $nameOrId): array
    {
        return $this->httpClient->request(
            'GET',
            self::BASE_URL . '/pokemon/' . $nameOrId
        )->toArray();
    }

    public function getPokemonSpecies(string|int $nameOrId): array
    {
        return $this->httpClient->request(
            'GET',
            self::BASE_URL . '/pokemon-species/' . $nameOrId
        )->toArray();
    }

    public function getAbility(string $url): array
    {
        return $this->httpClient->request('GET', $url)->toArray();
    }

    public function getPokemonListWithSprites(int $limit = 20, int $offset = 0): array
    {
        $pokemonList = $this->getPokemonList($limit, $offset);

        $pokemonData = [];
        foreach ($pokemonList['results'] as $pokemon) {
            $details = $this->getPokemon($pokemon['name']);
            $pokemonData[] = [
                'name' => $details['name'],
                'image' => $details['sprites']['other']['official-artwork']['front_default'] ?? null,
                'id' => $details['id']
            ];
        }

        return $pokemonData;
    }

    public function getPokemonDetails(string $name): array
    {
        $details = $this->getPokemon($name);

        // Get abilities
        $abilities = [];
        foreach ($details['abilities'] as $ability) {
            $abilityApiResponse = $this->getAbility($ability['ability']['url']);

            // Find English name
            $abilityName = $ability['ability']['name'];
            foreach ($abilityApiResponse['names'] as $nameEntry) {
                if ($nameEntry['language']['name'] === 'en') {
                    $abilityName = $nameEntry['name'];
                    break;
                }
            }

            // Find English effect
            $shortEffect = 'No description available';
            foreach ($abilityApiResponse['effect_entries'] as $effectEntry) {
                if ($effectEntry['language']['name'] === 'en') {
                    $shortEffect = $effectEntry['short_effect'];
                    break;
                }
            }

            $abilities[] = [
                'name' => $abilityName,
                'shortEffect' => $shortEffect,
            ];
        }

        // Get flavor text
        $flavorTextResponse = $this->getPokemonSpecies($name)['flavor_text_entries'];
        $englishFlavorText = null;

        foreach ($flavorTextResponse as $entry) {
            if ($entry['language']['name'] === 'en') {
                $englishFlavorText = $entry['flavor_text'];
                break;
            }
        }

        $flavorText = trim(preg_replace('/\s+/', ' ', str_replace(["\n", "\f", "\r"], ' ', $englishFlavorText)));

        // Get types
        $pokeTypes = [];
        foreach ($details['types'] as $type) {
            $pokeTypes[] = [
                'slot' => $type['slot'],
                'name' => $type['type']['name'],
                'id' => explode("/", parse_url($type['type']['url'])['path'])[4]
            ];
        }

        return [
            'name' => $details['name'],
            'image' => $details['sprites']['other']['official-artwork']['front_default'] ?? null,
            'id' => $details['id'],
            'types' => $pokeTypes,
            'flavorText' => $flavorText,
            'abilities' => $abilities
        ];
    }

    public function getPokemonByType(string $typeName): array
    {
        $response = $this->httpClient->request(
            'GET',
            self::BASE_URL . '/type/' . $typeName
        )->toArray();

        return $response['pokemon'] ?? [];
    }

    public function searchPokemon(?string $name = null, array $types = []): array
    {
        $pokemonData = [];

        // If searching by name only
        if ($name && empty($types)) {
            try {
                $details = $this->getPokemon(strtolower($name));
                $pokemonData[] = [
                    'name' => $details['name'],
                    'image' => $details['sprites']['other']['official-artwork']['front_default'] ?? null,
                    'id' => $details['id']
                ];
            } catch (\Exception $e) {
                // Pokemon not found, return empty array
                return [];
            }
        }
        // If filtering by types
        elseif (!empty($types)) {
            $pokemonByType = [];

            // Fetch pokemon for each type
            foreach ($types as $type) {
                $typeData = $this->getPokemonByType($type);
                $pokemonNames = array_map(fn($p) => $p['pokemon']['name'], $typeData);
                $pokemonByType[] = $pokemonNames;
            }

            // Intersect if multiple types selected (Pokemon must have ALL selected types)
            $filteredPokemon = count($pokemonByType) > 1
                ? array_intersect(...$pokemonByType)
                : $pokemonByType[0];

            // If name is also provided, filter by name
            if ($name) {
                $filteredPokemon = array_filter(
                    $filteredPokemon,
                    fn($pokemonName) => stripos($pokemonName, strtolower($name)) !== false
                );
            }

            // Fetch details for filtered pokemon (limit to 50 for performance)
            $filteredPokemon = array_slice($filteredPokemon, 0, 20);

            foreach ($filteredPokemon as $pokemonName) {
                try {
                    $details = $this->getPokemon($pokemonName);
                    $pokemonData[] = [
                        'name' => $details['name'],
                        'image' => $details['sprites']['other']['official-artwork']['front_default'] ?? null,
                        'id' => $details['id']
                    ];
                } catch (\Exception $e) {
                    // Skip if unable to fetch details
                    continue;
                }
            }
        }

        return $pokemonData;
    }
}
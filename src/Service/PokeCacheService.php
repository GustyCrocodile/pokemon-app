<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PokeCacheService
{
    private $cache;
    private $pokeApiClient;

    public function __construct(CacheInterface $cache, PokeApiClient $pokeApiClient)
    {
        $this->cache = $cache;
        $this->pokeApiClient = $pokeApiClient;
    }

    /**
     * Get all cached Pokemon names from storage
     * Returns array of Pokemon names or empty array if cache is empty
     */
    public function getAllPokemonNames()
    {
        $cacheKey = 'pokemon_all_names';

        $cachedNames = $this->cache->get($cacheKey, function (ItemInterface $item) {
            // Cache expires after 30 days
            $item->expiresAfter(86400 * 30);
            return [];
        });

        return $cachedNames;
    }

    /**
     * Fetch all Pokemon names from API and store in cache
     * This should be run once to populate the cache
     */
    public function populateNamesCache()
    {
        // Fetch all Pokemon from API (limit=10000 gets all of them)
        $response = $this->pokeApiClient->getPokemonList(10000, 0);
        $allPokemonNames = $response['results'];

        // Clear old cache
        $cacheKey = 'pokemon_all_names';
        $this->cache->delete($cacheKey);

        // Save to cache
        $this->cache->get($cacheKey, function (ItemInterface $item) use ($allPokemonNames) {
            $item->expiresAfter(86400 * 30); // 30 days
            return $allPokemonNames;
        });

        return count($allPokemonNames);
    }

    /**
     * Search Pokemon names by partial match
     * Returns array of matching Pokemon names
     */
    public function searchPokemonNames($searchQuery)
    {
        $allNames = $this->getAllPokemonNames();

        if (empty($allNames)) {
            return [];
        }

        $matches = [];
        $searchLower = strtolower($searchQuery);

        foreach ($allNames as $pokemon) {
            $pokemonName = $pokemon['name'];

            // Check if search query is found in pokemon name
            if (strpos($pokemonName, $searchLower) !== false) {
                $matches[] = $pokemon;
            }
        }

        return $matches;
    }

    /**
     * Check if the cache has been populated with Pokemon names
     */
    public function isCachePopulated()
    {
        $names = $this->getAllPokemonNames();
        return count($names) > 0;
    }
}
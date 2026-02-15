<?php

namespace App\Controller;

use App\Service\PokeApiClient;
use App\Service\PokeCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PokemonController extends AbstractController
{
    private $pokeApiClient;
    private $pokeCacheService;

    public function __construct(PokeApiClient $pokeApiClient, PokeCacheService $pokeCacheService)
    {
        $this->pokeApiClient = $pokeApiClient;
        $this->pokeCacheService = $pokeCacheService;
    }

    #[Route('/', name: 'pokemon_index')]
    public function index(Request $request): Response
    {
        $page = $request->query->get('page', 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $types = $this->pokeApiClient->getTypes();

        // Get Pokemon list from API (just names and URLs)
        $pokemonList = $this->pokeApiClient->getPokemonList($perPage, $offset);
        $totalCount = $pokemonList['count'];
        $totalPages = ceil($totalCount / $perPage);

        // Pass Pokemon names, frontend will load images
        $pokemonData = [];
        foreach ($pokemonList['results'] as $pokemon) {
            $pokemonData[] = [
                'name' => $pokemon['name'],
                'url' => $pokemon['url']
            ];
        }

        return $this->render('pokemon/index.html.twig', [
            'types' => $types['results'],
            'pokemon' => $pokemonData,
            'count' => count($pokemonData),
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount
        ]);
    }

    #[Route('/pokemon/{name}', name: 'pokemon_show')]
    public function show(string $name): Response
    {
        $types = $this->pokeApiClient->getTypes();
        $pokeData = $this->pokeApiClient->getPokemonDetails($name);

        return $this->render('pokemon/show.html.twig', [
            'poke' => $pokeData,
            'types' => $types['results'],
        ]);
    }

    #[Route('/search', name: 'pokemon_search')]
    public function search(Request $request): Response
    {
        $name = $request->query->get('name', '');
        $selectedTypes = $request->query->all('types') ?? [];

        $types = $this->pokeApiClient->getTypes();
        $pokemon = [];
        $pokemonNames = [];

        // Get Pokemon names based on search criteria
        if ($name && empty($selectedTypes)) {
            // Search by name only from cache
            $matches = $this->pokeCacheService->searchPokemonNames($name);
            foreach ($matches as $match) {
                $pokemonNames[] = $match['name'];
            }
        } elseif (!empty($selectedTypes)) {
            // Get Pokemon by type
            $pokemonByType = [];
            foreach ($selectedTypes as $type) {
                $typeData = $this->pokeApiClient->getPokemonByType($type);
                $names = array_map(function($item) {
                    return $item['pokemon']['name'];
                }, $typeData);
                $pokemonByType[] = $names;
            }

            // Intersect if multiple types
            $pokemonNames = count($pokemonByType) > 1 ? array_intersect(...$pokemonByType) : $pokemonByType[0];

            // Filter by name if provided
            if ($name) {
                $pokemonNames = array_filter($pokemonNames, function($pokemonName) use ($name) {
                    return strpos($pokemonName, strtolower($name)) !== false;
                });
            }

            $pokemonNames = array_slice($pokemonNames, 0, 20);
        }

        // Fetch details for all matching Pokemon
        foreach ($pokemonNames as $pokemonName) {
            $details = $this->pokeApiClient->getPokemon($pokemonName);
            $pokemon[] = [
                'name' => $details['name'],
                'image' => $details['sprites']['other']['official-artwork']['front_default'] ?? null,
                'id' => $details['id']
            ];
        }

        if (($name || !empty($selectedTypes)) && empty($pokemon)) {
            $this->addFlash('warning', 'No Pokémon found matching your search criteria.');
        }

        return $this->render('pokemon/index.html.twig', [
            'types' => $types['results'],
            'pokemon' => $pokemon,
            'count' => count($pokemon),
            'searchName' => $name,
            'selectedTypes' => $selectedTypes
        ]);
    }
}

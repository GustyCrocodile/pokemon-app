<?php

namespace App\Controller;

use App\Service\PokeApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PokemonController extends AbstractController
{
    public function __construct(
        private PokeApiClient $pokeApiClient
    ) {
    }

    #[Route('/', name: 'pokemon_index')]
    public function index(): Response
    {
        $types = $this->pokeApiClient->getTypes();
        $pokemonData = $this->pokeApiClient->getPokemonListWithSprites(20);

        return $this->render('pokemon/index.html.twig', [
            'types' => $types['results'],
            'pokemon' => $pokemonData,
            'count' => count($pokemonData)
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

        // search
        $pokemon = [];
        if ($name || !empty($selectedTypes)) {
            $pokemon = $this->pokeApiClient->searchPokemon($name ?: null, $selectedTypes);

            if (empty($pokemon)) {
                $this->addFlash('warning', 'No Pokémon found matching your search criteria.');
            }
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

<?php

namespace App\Controller;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PokemonController extends AbstractController
{
    #[Route('/', name: 'pokemon_index')]
    public function index(HttpClientInterface $httpClient): Response
    {
        $types = $httpClient->request(
            'GET',
            'https://pokeapi.co/api/v2/type',
        )->toArray();

        // dd( $types );

        // Get list of pokemon
        $pokemonList = $httpClient->request(
            'GET',
            'https://pokeapi.co/api/v2/pokemon?limit=20',
        )->toArray();

        // Fetch detailed data for each pokemon to get sprites
        $pokemonData = [];
        foreach ($pokemonList['results'] as $pokemon) {
            $details = $httpClient->request('GET', $pokemon['url'])->toArray();
            $pokemonData[] = [
                'name' => $details['name'],
                'image' => $details['sprites']['other']['official-artwork']['front_default'] ?? null,
                'id' => $details['id']
            ];
        }

        return $this->render('pokemon/index.html.twig', [
            'types' => $types,
            'pokemon' => $pokemonData
        ]);
    }

    #[Route('/{name}', name: 'pokemon_show')]
    public function show(HttpClientInterface $httpClient, string $name): Response
    {

        $details = $httpClient->request(
            'GET',
            'https://pokeapi.co/api/v2/pokemon/' . $name,
        )->getContent();

        dd($details);

        return $this->render('pokemon/show.html.twig', [
            'details' => $details,
        ]);
    }
}

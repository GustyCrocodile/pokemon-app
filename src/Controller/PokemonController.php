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

        // Get pokemon list
        $pokemonList = $httpClient->request(
            'GET',
            'https://pokeapi.co/api/v2/pokemon?limit=20',
        )->toArray();

        // get sprites pokemon
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
        )->toArray();

        $abilities = [];
        foreach ($details['abilities'] as $ability) {
            $abilityApiResponse = $httpClient->request(
                'GET',
                $ability['ability']['url'],
            )->toArray();


            $abilities[] = [
                'name' => $abilityApiResponse['names'][7]['name'],
                'shortEffect' => $abilityApiResponse['effect_entries'][2]['short_effect'],
            ];
        }
        
        $flavorTextResponse = $httpClient->request(
            'GET',
            'https://pokeapi.co/api/v2/pokemon-species/' . $name,
        )->toArray()['flavor_text_entries'];

        $englishFlavorText = null;

        foreach ($flavorTextResponse as $index => $entry) {
            if ($entry['language']['name'] === 'en') {
                $englishFlavorText = $entry['flavor_text'];
                break;
            }
        }

        $flavorText = trim(preg_replace('/\s+/', ' ', str_replace(["\n", "\f", "\r"], ' ', $englishFlavorText)));

        $pokeTypes = [];
        foreach ($details['types'] as $type) {
            $pokeTypes[] = [
                'slot' => $type['slot'],
                'name' => $type['type']['name'],
                'id' => explode("/", parse_url($type['type']['url'])['path'])[4]
            ];
        }

        $pokeData = [
            'name' => $details['name'],
            'image' => $details['sprites']['other']['official-artwork']['front_default'] ?? null,
            'id' => $details['id'],
            'types' => $pokeTypes,
            'flavorText' => $flavorText,
            'abilities' => $abilities 
        ];
    
        return $this->render('pokemon/show.html.twig', [
            'poke' => $pokeData,
        ]);
    }
}

<?php

namespace NeuronAI\Tests;

use NeuronAI\RAG\Document;
use NeuronAI\RAG\VectorStore\TypesenseVectorStore;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;
use PHPUnit\Framework\TestCase;
use Typesense\Client;

class TypesenseTest extends TestCase
{
    protected Client $client;

    protected int $vectorDimension;

    protected array $embedding;

    protected function setUp(): void
    {
        if (!$this->isPortOpen('127.0.0.1', 8108)) {
            $this->markTestSkipped('Port 8108 is not open. Skipping test.');
        }

        // see getting started
        // https://typesense.org/docs/guide/install-typesense.html#option-2-local-machine-self-hosting

        $this->client = new Client(
            [
                'api_key' => 'xyz',
                'nodes' => [
                    [
                        'host' => '127.0.0.1',
                        'port' => '8108',
                        'protocol' => 'http'
                    ],
                ]
            ]
        );

        // used bge-multilingual-gemma2 to generate the embedding
        $this->vectorDimension = 3584;

        // embedding "Hello World!"
        $this->embedding = json_decode(file_get_contents(__DIR__ . '/stubs/hello-world.embeddings'), true);
    }

    private function isPortOpen(string $host, int $port, int $timeout = 1): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }

    public function testTypesenseInstance()
    {
        $store = new TypesenseVectorStore($this->client, 'test', $this->vectorDimension);
        $this->assertInstanceOf(VectorStoreInterface::class, $store);
    }

    public function testAddDocument()
    {
        $this->expectNotToPerformAssertions();

        $document = new Document('Hello World!');
        $document->embedding = $this->embedding;
        $document->hash = \hash('sha256', 'Hello World!' . time()); // added time() to avoid exception 'A document with id x already exists'

        $store = new TypesenseVectorStore($this->client, 'test', $this->vectorDimension);

        $store->addDocument($document);
    }

    public function testSearch()
    {
        $store = new TypesenseVectorStore($this->client, 'test', $this->vectorDimension);

        $results = $store->similaritySearch($this->embedding);
        $this->assertIsArray($results);
    }
}

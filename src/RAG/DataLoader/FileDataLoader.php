<?php

namespace NeuronAI\RAG\DataLoader;

use NeuronAI\RAG\Document;
use NeuronAI\RAG\Splitters\DocumentSplitter;

class FileDataLoader implements DataLoaderInterface
{
    /**
     * @var array<string, ReaderInterface>
     */
    protected array $readers = [];

    public function __construct(protected string $path, array $readers = [])
    {
        $this->setReaders($readers);
    }

    public function addReader(string $fileExtension, ReaderInterface $reader): self
    {
        $this->readers[$fileExtension] = $reader;
        return $this;
    }

    /**
     * @param array $readers
     * @return FileDataLoader
     */
    public function setReaders(array $readers): self
    {
        $this->readers = $readers;
        return $this;
    }

    public function getDocuments(): array
    {
        if (! file_exists($this->path)) {
            return [];
        }

        // If it's a directory
        if (is_dir($this->path)) {
            return $this->getDocumentsFromDirectory($this->path);
        }

        // If it's a file
        try {
            return [$this->getDocument($this->getContentFromFile($this->path), $this->path)];
        } catch (\Throwable $exception) {
            return [];
        }
    }

    protected function getDocumentsFromDirectory(string $directory): array
    {
        $documents = [];
        // Open the directory
        if ($handle = opendir($directory)) {
            // Read the directory contents
            while (($entry = readdir($handle)) !== false) {
                $fullPath = $directory.'/'.$entry;
                if ($entry != '.' && $entry != '..') {
                    if (is_dir($fullPath)) {
                        $documents = [...$documents, ...$this->getDocumentsFromDirectory($fullPath)];
                    } else {
                        try {
                            $documents[] = $this->getDocument($this->getContentFromFile($fullPath), $entry);
                        } catch (\Throwable $exception) {}
                    }
                }
            }

            // Close the directory
            closedir($handle);
        }

        return DocumentSplitter::splitDocuments($documents);
    }

    /**
     * Transform files to plain text.
     *
     * Supported PDF and plain text files.
     *
     * @throws \Exception
     */
    protected function getContentFromFile(string $path): string|false
    {
        $fileExtension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (\array_key_exists($fileExtension, $this->readers)) {
            $reader = $this->readers[$fileExtension];
            return $reader::getText($path);
        }

        return TextFileReader::getText($path);
    }


    protected function getDocument(string $content, string $entry): mixed
    {
        $document = new Document($content);
        $document->sourceType = 'files';
        $document->sourceName = $entry;
        $document->hash = \hash('sha256', $content);

        return $document;
    }
}

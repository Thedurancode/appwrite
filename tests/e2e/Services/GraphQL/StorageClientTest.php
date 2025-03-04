<?php

namespace Tests\E2E\Services\GraphQL;

use CURLFile;
use Tests\E2E\Client;
use Tests\E2E\Scopes\ProjectCustom;
use Tests\E2E\Scopes\Scope;
use Tests\E2E\Scopes\SideClient;
use Utopia\Database\ID;
use Utopia\Database\Permission;
use Utopia\Database\Role;

class StorageClientTest extends Scope
{
    use ProjectCustom;
    use SideClient;
    use Base;

    public function testCreateBucket(): array
    {
        $projectId = $this->getProject()['$id'];
        $query = $this->getQuery(self::$CREATE_BUCKET);
        $gqlPayload = [
            'query' => $query,
            'variables' => [
                'bucketId' => 'actors',
                'name' => 'Actors',
                'fileSecurity' => false,
                'permissions' => [
                    Permission::read(Role::any()),
                    Permission::create(Role::any()),
                    Permission::update(Role::any()),
                    Permission::delete(Role::any()),
                ],
            ]
        ];

        $bucket = $this->client->call(Client::METHOD_POST, '/graphql', [
            'content-type' => 'application/json',
            'x-appwrite-project' => $projectId,
            'x-appwrite-key' => $this->getProject()['apiKey'],
        ], $gqlPayload);

        $this->assertIsArray($bucket['body']['data']);
        $this->assertArrayNotHasKey('errors', $bucket['body']);
        $bucket = $bucket['body']['data']['storageCreateBucket'];
        $this->assertEquals('Actors', $bucket['name']);

        return $bucket;
    }

    /**
     * @depends testCreateBucket
     */
    public function testCreateFile($bucket): array
    {
        $projectId = $this->getProject()['$id'];
        $query = $this->getQuery(self::$CREATE_FILE);
        $gqlPayload = [
            'operations' => \json_encode([
                'query' => $query,
                'variables' => [
                    'bucketId' => $bucket['_id'],
                    'fileId' => ID::unique(),
                    'file' => null,
                    'fileSecurity' => true,
                    'permissions' => [
                        Permission::read(Role::any()),
                        Permission::update(Role::any()),
                        Permission::delete(Role::any()),
                    ],
                ]
            ]),
            'map' => \json_encode([
                '0' => ["variables.file"]
            ]),
            '0' => new CURLFile(realpath(__DIR__ . '/../../../resources/logo.png'), 'image/png', 'logo.png'),
        ];

        $file = $this->client->call(Client::METHOD_POST, '/graphql', \array_merge([
            'content-type' => 'multipart/form-data',
            'x-appwrite-project' => $projectId,
        ], $this->getHeaders()), $gqlPayload);

        $this->assertIsArray($file['body']['data']);
        $this->assertArrayNotHasKey('errors', $file['body']);

        return $file['body']['data']['storageCreateFile'];
    }

    /**
     * @depends testCreateBucket
     * @param $bucket
     * @return array
     * @throws \Exception
     */
    public function testGetFiles($bucket): array
    {
        $projectId = $this->getProject()['$id'];
        $query = $this->getQuery(self::$GET_FILES);
        $gqlPayload = [
            'query' => $query,
            'variables' => [
                'bucketId' => $bucket['_id'],
            ]
        ];

        $files = $this->client->call(Client::METHOD_POST, '/graphql', \array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $projectId,
        ], $this->getHeaders()), $gqlPayload);

        $this->assertIsArray($files['body']['data']);
        $this->assertArrayNotHasKey('errors', $files['body']);
        $files = $files['body']['data']['storageListFiles'];
        $this->assertIsArray($files);

        return $files;
    }

    /**
     * @depends testCreateBucket
     * @depends testCreateFile
     * @param $bucket
     * @param $file
     * @return array
     * @throws \Exception
     */
    public function testGetFile($bucket, $file)
    {
        $projectId = $this->getProject()['$id'];
        $query = $this->getQuery(self::$GET_FILE);
        $gqlPayload = [
            'query' => $query,
            'variables' => [
                'bucketId' => $bucket['_id'],
                'fileId' => $file['_id'],
            ]
        ];

        $file = $this->client->call(Client::METHOD_POST, '/graphql', \array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $projectId,
        ], $this->getHeaders()), $gqlPayload);

        $this->assertIsArray($file['body']['data']);
        $this->assertArrayNotHasKey('errors', $file['body']);

        return $file['body']['data']['storageGetFile'];
    }

    /**
     * @depends testCreateFile
     * @param $file
     * @return array
     * @throws \Exception
     */
    public function testGetFilePreview($file)
    {
        $projectId = $this->getProject()['$id'];
        $query = $this->getQuery(self::$GET_FILE_PREVIEW);
        $gqlPayload = [
            'query' => $query,
            'variables' => [
                'bucketId' => $file['bucketId'],
                'fileId' => $file['_id'],
                'width' => 100,
                'height' => 100,
            ]
        ];

        $file = $this->client->call(Client::METHOD_POST, '/graphql', \array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $projectId,
        ], $this->getHeaders()), $gqlPayload);

        $this->assertEquals(46719, \strlen($file['body']));

        return $file;
    }

    /**
     * @depends testCreateFile
     * @param $file
     * @return array
     * @throws \Exception
     */
    public function testGetFileDownload($file)
    {
        $projectId = $this->getProject()['$id'];
        $query = $this->getQuery(self::$GET_FILE_DOWNLOAD);
        $gqlPayload = [
            'query' => $query,
            'variables' => [
                'bucketId' => $file['bucketId'],
                'fileId' => $file['_id'],
            ]
        ];

        $file = $this->client->call(Client::METHOD_POST, '/graphql', \array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $projectId,
        ], $this->getHeaders()), $gqlPayload);

        $this->assertEquals(47218, \strlen($file['body']));
    }

    /**
     * @depends testCreateFile
     * @param $file
     * @return array
     * @throws \Exception
     */
    public function testGetFileView($file): void
    {
        $projectId = $this->getProject()['$id'];
        $query = $this->getQuery(self::$GET_FILE_VIEW);
        $gqlPayload = [
            'query' => $query,
            'variables' => [
                'bucketId' => $file['bucketId'],
                'fileId' => $file['_id'],
            ]
        ];

        $file = $this->client->call(Client::METHOD_POST, '/graphql', \array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $projectId,
        ], $this->getHeaders()), $gqlPayload);

        $this->assertEquals(47218, \strlen($file['body']));
    }

    /**
     * @depends testCreateFile
     * @param $file
     * @return array
     * @throws \Exception
     */
    public function testUpdateFile($file): array
    {
        $projectId = $this->getProject()['$id'];
        $query = $this->getQuery(self::$UPDATE_FILE);
        $gqlPayload = [
            'query' => $query,
            'variables' => [
                'bucketId' => $file['bucketId'],
                'fileId' => $file['_id'],
                'permissions' => [
                    Permission::read(Role::any()),
                    Permission::update(Role::any()),
                    Permission::delete(Role::any()),
                ],
            ]
        ];

        $file = $this->client->call(Client::METHOD_POST, '/graphql', \array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $projectId,
        ], $this->getHeaders()), $gqlPayload);

        $this->assertIsArray($file['body']['data']);
        $this->assertArrayNotHasKey('errors', $file['body']);
        $file = $file['body']['data']['storageUpdateFile'];
        $this->assertIsArray($file);

        return $file;
    }

    /**
     * @depends testCreateFile
     * @param $file
     * @throws \Exception
     */
    public function testDeleteFile($file): void
    {
        $projectId = $this->getProject()['$id'];
        $query = $this->getQuery(self::$DELETE_FILE);
        $gqlPayload = [
            'query' => $query,
            'variables' => [
                'bucketId' => $file['bucketId'],
                'fileId' => $file['_id'],
            ]
        ];

        $file = $this->client->call(Client::METHOD_POST, '/graphql', \array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $projectId,
        ], $this->getHeaders()), $gqlPayload);

        $this->assertIsNotArray($file['body']);
        $this->assertEquals(204, $file['headers']['status-code']);
    }
}

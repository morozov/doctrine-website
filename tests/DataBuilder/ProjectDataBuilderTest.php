<?php

declare(strict_types=1);

namespace Doctrine\Website\Tests\DataBuilder;

use DateTimeImmutable;
use Doctrine\Website\DataBuilder\ProjectDataBuilder;
use Doctrine\Website\Docs\RST\RSTLanguage;
use Doctrine\Website\Docs\RST\RSTLanguagesDetector;
use Doctrine\Website\Git\Tag;
use Doctrine\Website\Projects\GetProjectPackagistData;
use Doctrine\Website\Projects\ProjectDataReader;
use Doctrine\Website\Projects\ProjectDataRepository;
use Doctrine\Website\Projects\ProjectGitSyncer;
use Doctrine\Website\Projects\ProjectVersionsReader;
use Doctrine\Website\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ProjectDataBuilderTest extends TestCase
{
    /** @var ProjectDataRepository|MockObject */
    private $projectDataRepository;

    /** @var ProjectGitSyncer|MockObject */
    private $projectGitSyncer;

    /** @var ProjectDataReader|MockObject */
    private $projectDataReader;

    /** @var ProjectVersionsReader|MockObject */
    private $projectVersionsReader;

    /** @var RSTLanguagesDetector|MockObject */
    private $rstLanguagesDetector;

    /** @var GetProjectPackagistData|MockObject */
    private $getProjectPackagistData;

    /** @var string */
    private $projectsDir;

    /** @var ProjectDataBuilder */
    private $projectDataBuilder;

    public function testBuild() : void
    {
        $this->projectDataRepository->expects(self::once())
            ->method('getProjectRepositoryNames')
            ->willReturn(['orm']);

        $this->projectGitSyncer->expects(self::at(0))
            ->method('checkoutDefaultBranch')
            ->with('orm');

        $this->projectDataReader->expects(self::once())
            ->method('read')
            ->with('orm')
            ->willReturn([
                'composerPackageName' => 'doctrine/orm',
                'repositoryName' => 'orm',
                'docsPath' => '/docs',
                'versions' => [
                    [
                        'name' => '1.1',
                        'branchName' => '1.1',
                    ],
                ],
            ]);

        $this->projectVersionsReader->expects(self::once())
            ->method('readProjectVersions')
            ->with('/path/to/projects/orm')
            ->willReturn([
                [
                    'name' => '1.0',
                    'branchName' => '1.0',
                    'tags' => [
                        new Tag('1.0.0', new DateTimeImmutable('2019-09-01')),
                        new Tag('1.0.1', new DateTimeImmutable('2019-09-02')),
                    ],
                ],
                [
                    'name' => '1.1',
                    'branchName' => '1.1',
                    'tags' => [
                        new Tag('1.1.0', new DateTimeImmutable('2019-09-03')),
                        new Tag('1.1.1', new DateTimeImmutable('2019-09-04')),
                    ],
                ],
            ]);

        $this->projectGitSyncer->expects(self::at(1))
            ->method('checkoutBranch')
            ->with('orm', '1.1');

        $this->rstLanguagesDetector->expects(self::at(0))
            ->method('detectLanguages')
            ->with('/path/to/projects/orm/docs')
            ->willReturn([
                new RSTLanguage('en', '/path/to/en'),
            ]);

        $this->projectGitSyncer->expects(self::at(2))
            ->method('checkoutBranch')
            ->with('orm', '1.0');

        $this->rstLanguagesDetector->expects(self::at(1))
            ->method('detectLanguages')
            ->with('/path/to/projects/orm/docs')
            ->willReturn([]);

        $this->getProjectPackagistData->expects(self::once())
            ->method('__invoke')
            ->with('doctrine/orm')
            ->willReturn(['package' => []]);

        $data = $this->projectDataBuilder->build()->getData();

        $expected = [
            [
                'active' => true,
                'archived' => false,
                'integration' => false,
                'composerPackageName' => 'doctrine/orm',
                'repositoryName' => 'orm',
                'docsPath' => '/docs',
                'versions' => [
                    [
                        'name' => '1.1',
                        'branchName' => '1.1',
                        'tags' => [
                            [
                                'name' => '1.1.0',
                                'date' => '2019-09-03 00:00:00',
                            ],
                            [
                                'name' => '1.1.1',
                                'date' => '2019-09-04 00:00:00',
                            ],
                        ],
                        'hasDocs' => true,
                        'docsLanguages' => [
                            [
                                'code' =>  'en',
                                'path' => '/path/to/en',
                            ],
                        ],
                    ],
                    [
                        'name' => '1.0',
                        'branchName' => '1.0',
                        'tags' => [
                            [
                                'name' => '1.0.0',
                                'date' => '2019-09-01 00:00:00',
                            ],
                            [
                                'name' => '1.0.1',
                                'date' => '2019-09-02 00:00:00',
                            ],
                        ],
                        'maintained' => false,
                        'hasDocs' => false,
                        'docsLanguages' => [],
                    ],
                ],
                'packagistData' => ['package' => []],
            ],
        ];

        self::assertSame($expected, $data);
    }

    protected function setUp() : void
    {
        $this->projectDataRepository   = $this->createMock(ProjectDataRepository::class);
        $this->projectGitSyncer        = $this->createMock(ProjectGitSyncer::class);
        $this->projectDataReader       = $this->createMock(ProjectDataReader::class);
        $this->projectVersionsReader   = $this->createMock(ProjectVersionsReader::class);
        $this->rstLanguagesDetector    = $this->createMock(RSTLanguagesDetector::class);
        $this->getProjectPackagistData = $this->createMock(GetProjectPackagistData::class);
        $this->projectsDir             = '/path/to/projects';

        $this->projectDataBuilder = new ProjectDataBuilder(
            $this->projectDataRepository,
            $this->projectGitSyncer,
            $this->projectDataReader,
            $this->projectVersionsReader,
            $this->rstLanguagesDetector,
            $this->getProjectPackagistData,
            $this->projectsDir
        );
    }
}

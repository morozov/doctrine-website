<?php

declare(strict_types=1);

namespace Doctrine\Website\Projects;

use Doctrine\Website\ProcessFactory;
use Github\Client;
use function is_dir;
use function sprintf;

class ProjectGitSyncer
{
    /** @var ProcessFactory */
    private $processFactory;

    /** @var string */
    private $projectsDir;

    /** @var \Github\Api\Repo */
    private $githubRepo;

    public function __construct(ProcessFactory $processFactory, Client $githubClient, string $projectsDir)
    {
        $this->processFactory = $processFactory;
        $this->projectsDir    = $projectsDir;
        $this->githubRepo     = $githubClient->repo();
    }

    public function isRepositoryInitialized(string $repositoryName) : bool
    {
        return is_dir($this->projectsDir . '/' . $repositoryName . '/.git');
    }

    public function initRepository(string $repositoryName) : void
    {
        $repositoryPath = $this->projectsDir . '/' . $repositoryName;

        if (is_dir($repositoryPath)) {
            return;
        }

        $command = sprintf(
            'git clone https://github.com/doctrine/%s.git %s',
            $repositoryName,
            $repositoryPath
        );

        $this->processFactory->run($command);
    }

    public function checkoutDefaultBranch(string $repositoryName) : void
    {
        $repoMetaData = $this->githubRepo->show('doctrine', $repositoryName);

        $this->checkoutBranch($repositoryName, $repoMetaData['default_branch']);
    }

    public function checkoutBranch(string $repositoryName, string $branchName) : void
    {
        $command = sprintf(
            'cd %s && git clean -xdf && git checkout origin/%s',
            $this->getRepositoryPath($repositoryName),
            $branchName
        );

        $this->processFactory->run($command);
    }

    public function syncRepository(string $repositoryName) : void
    {
        $command = sprintf(
            'cd %s && git clean -xdf && git fetch origin',
            $this->getRepositoryPath($repositoryName)
        );

        $this->processFactory->run($command);
    }

    private function getRepositoryPath(string $repositoryName) : string
    {
        return $this->projectsDir . '/' . $repositoryName;
    }
}

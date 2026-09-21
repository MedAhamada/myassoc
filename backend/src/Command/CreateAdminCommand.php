<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Admin;
use App\Repository\AdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Bootstraps the first administrator account: the /api/admins CRUD requires
 * an authenticated admin, so someone has to exist before anyone can log in.
 */
#[AsCommand(name: 'app:admin:create', description: 'Create an administrator account')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminRepository $adminRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
            ->addArgument('nom', InputArgument::REQUIRED)
            ->addArgument('prenom', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        if (null !== $this->adminRepository->findOneByEmail($email)) {
            $io->error(sprintf('Un administrateur existe déjà avec l\'email "%s".', $email));

            return Command::FAILURE;
        }

        $admin = new Admin();
        $admin->setEmail($email)
            ->setNom((string) $input->getArgument('nom'))
            ->setPrenom((string) $input->getArgument('prenom'));
        $admin->setPassword($this->passwordHasher->hashPassword($admin, (string) $input->getArgument('password')));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success(sprintf('Administrateur "%s" créé.', $email));

        return Command::SUCCESS;
    }
}

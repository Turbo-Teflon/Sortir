<?php

namespace App\Command;

use App\Repository\SortieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Entity\Etat;

#[AsCommand(
    name: 'app:update-sortie-status',
    description: 'update sortie status',
)]
class UpdateSortieStatusCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private SortieRepository $sortieRepository;

    public function __construct(EntityManagerInterface $entityManager, SortieRepository $sortieRepository)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->sortieRepository = $sortieRepository;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Début de la mise à jour des statuts de sorties');

        // 1. On cherche les sorties à mettre à jour
        $sortiesToUpdate = $this->sortieRepository->createQueryBuilder('s')
            ->where('s.etat = :ouvert')
            ->andWhere('s.start_date_time <= :now')
            ->setParameter('ouvert', Etat::OU->value)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();

        if (empty($sortiesToUpdate)) {
            $io->info('Aucune sortie à mettre à jour.');

            return Command::SUCCESS;
        }

        $io->progressStart(count($sortiesToUpdate));

        // 2. On boucle sur les sorties trouvées et on change leur état
        foreach ($sortiesToUpdate as $sortie) {
            $sortie->setEtat('En cours'); // Remplacez par le nom exact de votre état cible
            $this->entityManager->persist($sortie);
            $io->progressAdvance();
        }

        // 3. On enregistre TOUS les changements en base de données
        $this->entityManager->flush();

        $io->progressFinish();
        $io->success(count($sortiesToUpdate).' sortie(s) mise(s) à jour avec succès !');

        return Command::SUCCESS;
    }
}

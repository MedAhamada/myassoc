<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Adherant;
use App\Enum\AdherantStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Adherant>
 */
class AdherantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Adherant::class);
    }

    public function findOneByPublicUuid(Uuid $uuid): ?Adherant
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    /**
     * @return array{items: Adherant[], total: int}
     */
    public function search(?string $query, ?AdherantStatut $statut, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.nom', 'ASC')
            ->addOrderBy('a.prenom', 'ASC');

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('a.nom LIKE :q OR a.prenom LIKE :q OR a.email LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }

        if (null !== $statut) {
            $qb->andWhere('a.statut = :statut')
                ->setParameter('statut', $statut);
        }

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb, fetchJoinCollection: false);

        return [
            'items' => iterator_to_array($paginator->getIterator()),
            'total' => count($paginator),
        ];
    }
}

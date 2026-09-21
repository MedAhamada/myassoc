<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Adherant;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class MembershipMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly MembershipCardService $cardService,
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {
    }

    public function sendCard(Adherant $adherant): void
    {
        if (null === $adherant->getEmail()) {
            throw new \DomainException('Cet adhérent n\'a pas d\'adresse e-mail renseignée.');
        }

        $pdf = $this->cardService->generatePdf($adherant);

        $email = (new Email())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($adherant->getEmail())
            ->subject('Votre carte d\'adhérent MyAssoc')
            ->text(sprintf(
                "Bonjour %s,\n\nVeuillez trouver ci-joint votre carte d'adhérent.\n\nCordialement,\nL'équipe MyAssoc",
                $adherant->getPrenom()
            ))
            ->attach($pdf, 'carte-adherent.pdf', 'application/pdf');

        $this->mailer->send($email);
    }
}

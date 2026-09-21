<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Adherant;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

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

        $adhesion = $adherant->getCurrentAdhesion();
        $pdf = $this->cardService->generatePdf($adherant);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($adherant->getEmail())
            ->subject('Votre carte d\'adhérent MyAssoc')
            ->htmlTemplate('email/membership_card.html.twig')
            ->textTemplate('email/membership_card.txt.twig')
            ->context([
                'adherant' => $adherant,
                'adhesion' => $adhesion,
                'numeroAdhesion' => $adhesion ? $this->cardService->formatAdhesionNumber($adhesion) : null,
                'verificationUrl' => $this->cardService->getVerificationUrl($adherant),
            ])
            ->attach($pdf, 'carte-adherent.pdf', 'application/pdf');

        $logo = $this->cardService->getLogoPath();
        if (null !== $logo) {
            $email->embedFromPath($logo, 'logo');
        }

        $this->mailer->send($email);
    }
}

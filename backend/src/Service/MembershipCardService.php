<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Adherant;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Twig\Environment;

/**
 * Builds the membership card: a QR code pointing at the public verification
 * page, rendered into a PDF (credit-card sized).
 */
final class MembershipCardService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $frontendUrl,
    ) {
    }

    public function getVerificationUrl(Adherant $adherant): string
    {
        return rtrim($this->frontendUrl, '/').'/verify/'.$adherant->getUuid();
    }

    public function generateQrCodeDataUri(Adherant $adherant): string
    {
        $builder = new Builder(
            writer: new PngWriter(),
            data: $this->getVerificationUrl($adherant),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 260,
            margin: 8,
        );
        $result = $builder->build();

        return $result->getDataUri();
    }

    public function generatePdf(Adherant $adherant): string
    {
        $adhesion = $adherant->getCurrentAdhesion();

        $html = $this->twig->render('pdf/membership_card.html.twig', [
            'adherant' => $adherant,
            'adhesion' => $adhesion,
            'qrCodeDataUri' => $this->generateQrCodeDataUri($adherant),
        ]);

        $options = new DompdfOptions();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        // Physical card size (85.6mm x 53.98mm) converted to points, landscape.
        $dompdf->setPaper([0, 0, 243, 153], 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }
}

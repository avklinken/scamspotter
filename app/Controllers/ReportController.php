<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\SubmissionRepository;
use App\Services\SeoService;

final class ReportController extends Controller
{
    public function form(Request $request): void
    {
        $this->render('public/report', [
            'seo' => (new SeoService())->metadata([
                'title' => 'Meld een verdachte situatie — ScamSpotter.nl',
                'description' => 'Meld een verdachte e-mail, website, telefoonnummer of andere scam aan ScamSpotter.',
            ]),
            'errors' => [],
        ]);
    }

    public function submit(Request $request): void
    {
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            http_response_code(419);
            echo 'Ongeldige sessie. Probeer opnieuw.';
            return;
        }
        if (trim((string) $request->post('website', '')) !== '') {
            Response::redirect(url('/melden?sent=1'));
        }

        $type = (string) $request->post('type', '');
        $allowed = ['message', 'website', 'email', 'phone', 'other'];
        $description = trim((string) $request->post('description', ''));
        $consent = (string) $request->post('consent', '') === '1';
        $errors = [];
        if (!in_array($type, $allowed, true)) {
            $errors[] = 'Kies wat je wilt melden.';
        }
        if (mb_strlen($description) < 10) {
            $errors[] = 'Geef een korte omschrijving van minimaal 10 tekens.';
        }
        if (!$consent) {
            $errors[] = 'Geef toestemming voor het verwerken van deze melding.';
        }
        $contactEmail = trim((string) $request->post('contact_email', ''));
        if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Vul een geldig contactadres in of laat het veld leeg.';
        }
        $upload = $_FILES['attachment'] ?? null;
        if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $errors[] = 'De bijlage kon niet worden verwerkt.';
            } elseif ((int) ($upload['size'] ?? 0) > (int) env('UPLOAD_MAX_BYTES', '5242880')) {
                $errors[] = 'De bijlage mag maximaal 5 MB zijn.';
            }
        }
        if ($errors !== []) {
            $this->render('public/report', [
                'seo' => (new SeoService())->metadata(['title' => 'Meld een verdachte situatie — ScamSpotter.nl']),
                'errors' => $errors,
            ], 422);
            return;
        }

        $ipHash = hash('sha256', $request->ip() . '|' . (string) env('APP_KEY', ''));
        $repository = new SubmissionRepository($this->db());
        $submissionId = $repository->create([
            'type' => $type,
            'description' => $description,
            'suspicious_text' => trim((string) $request->post('suspicious_text', '')),
            'submitted_url' => trim((string) $request->post('submitted_url', '')),
            'submitted_email' => trim((string) $request->post('submitted_email', '')),
            'submitted_phone' => trim((string) $request->post('submitted_phone', '')),
            'contact_email' => $contactEmail !== '' ? $contactEmail : null,
            'consent' => $consent,
            'ip_hash' => $ipHash,
            'user_agent_hash' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . (string) env('APP_KEY', '')),
        ]);

        if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $this->storeAttachment($repository, $submissionId, $upload);
        }
        flash('success', 'Bedankt voor je melding. We beoordelen de informatie zorgvuldig voordat er iets publiek wordt gemaakt.');
        Response::redirect(url('/melden?sent=1'));
    }

    /** @param array<string, mixed> $upload */
    private function storeAttachment(SubmissionRepository $repository, int $submissionId, array $upload): void
    {
        $tmp = (string) ($upload['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp) || !is_readable($tmp) || !class_exists('finfo')) {
            return;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
        ];
        if (!isset($allowed[$mime])) {
            return;
        }
        $filename = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
        $directory = BASE_PATH . '/storage/uploads';
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $target = $directory . '/' . $filename;
        if (!move_uploaded_file($tmp, $target)) {
            return;
        }
        $repository->addAttachment($submissionId, [
            'original_name' => mb_substr((string) ($upload['name'] ?? 'bijlage'), 0, 255),
            'stored_name' => $filename,
            'mime_type' => $mime,
            'size_bytes' => (int) ($upload['size'] ?? 0),
            'sha256' => hash_file('sha256', $target),
        ]);
    }
}

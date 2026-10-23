<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

use App\Domain\Revision\Enums\RevisionStatus;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use App\Models\XishieProject;

/**
 * NextActionService: menghitung "langkah berikutnya" yang konkret untuk sebuah
 * proyek — memandu pengguna alih-alih menuntut mereka menebak tombol mana yang
 * harus ditekan. Dipakai dashboard (widget Next Actions) & workspace.
 */
final class NextActionService
{
    /**
     * Kembalikan daftar langkah berikutnya berurutan (prioritas tertinggi dulu).
     *
     * @return array<int, array{key: string, label: string, detail: string, url: string, severity: string}>
     */
    public function forProject(XishieProject $project): array
    {
        $actions = [];
        $workspace = '/admin/academic-workspace-page?project='.$project->id;

        $stage = $project->current_stage;
        $record = $project->stages()->where('stage_name', $stage->value)->first();
        $sections = (int) count((array) ($record?->sections_content ?? []));
        $references = $project->references()->count();
        $verified = $project->references()->where('is_verified', true)->count();
        $recommendations = $project->recommendations()->count();
        $compliance = (float) ($record?->compliance_score ?? 0.0);
        $pendingApproval = $record?->status === StageStatus::WAITING_APPROVAL;

        // 1) Menunggu persetujuan (HITL).
        if ($pendingApproval) {
            $actions[] = [
                'key' => 'approve',
                'label' => 'Tinjau & setujui draf menunggu',
                'detail' => 'Ada draf di Approval Gate yang perlu disetujui atau direvisi.',
                'url' => $workspace,
                'severity' => 'warn',
            ];
        }

        // 2) Belum ada referensi.
        if ($references === 0) {
            $actions[] = [
                'key' => 'references',
                'label' => 'Cari referensi literatur',
                'detail' => 'Belum ada referensi. Cari paper agar sitasi & matriks literatur terisi.',
                'url' => $workspace,
                'severity' => 'alert',
            ];
        } elseif ($verified === 0) {
            $actions[] = [
                'key' => 'verify',
                'label' => 'Verifikasi referensi',
                'detail' => "{$references} referensi belum ada yang terverifikasi. Verifikasi sebelum menulis.",
                'url' => $workspace,
                'severity' => 'warn',
            ];
        }

        // 3) Tahap belum punya naskah.
        if ($sections === 0 && ! $pendingApproval) {
            $actions[] = [
                'key' => 'draft',
                'label' => 'Susun draf tahap '.$stage->label(),
                'detail' => 'Naskah tahap ini masih kosong. Susun draf lengkap dalam satu klik.',
                'url' => $workspace,
                'severity' => 'alert',
            ];
        }

        // 4) Kepatuhan rendah.
        if ($sections > 0 && $compliance > 0 && $compliance < 70.0) {
            $actions[] = [
                'key' => 'compliance',
                'label' => 'Perbaiki kepatuhan pedoman',
                'detail' => sprintf('Skor kepatuhan %.0f%% (< 70%%). Tinjau auditor pedoman di editor.', $compliance),
                'url' => $workspace,
                'severity' => 'warn',
            ];
        }

        // 5) Catatan revisi reviewer yang belum selesai.
        $unresolvedRevisions = $project->revisions()
            ->where('status', '!=', RevisionStatus::RESOLVED->value)
            ->count();

        if ($unresolvedRevisions > 0) {
            $actions[] = [
                'key' => 'revision',
                'label' => 'Selesaikan catatan revisi reviewer',
                'detail' => sprintf('Terdapat %d siklus revisi yang belum selesai. Tinjau checklist & side-by-side diff.', $unresolvedRevisions),
                'url' => '/admin/smart-revision-center-page?project='.$project->id,
                'severity' => 'warn',
            ];
        }

        // 6) Belum ada rekomendasi jurnal (relevan di tahap Proposal/Final).
        if ($recommendations === 0 && in_array($stage, [ProjectStage::PROPOSAL, ProjectStage::FINAL], true)) {
            $actions[] = [
                'key' => 'venue',
                'label' => 'Cocokkan venue jurnal',
                'detail' => 'Belum ada rekomendasi jurnal. Jalankan Venue Matcher.',
                'url' => '/admin/platform-matcher-page?project='.$project->id,
                'severity' => 'info',
            ];
        }

        // 7) Naskah skripsi tahap akhir siap dikonversi ke artikel jurnal.
        if ($project->document_type->isThesisFamily() && $stage === ProjectStage::FINAL && $sections > 0) {
            $finalRecord = $project->stages()->where('stage_name', ProjectStage::FINAL->value)->first();
            $pending = (array) ($finalRecord?->hitl_pending_payload ?? []);
            if (! isset($pending['extracted_journal_manuscript'])) {
                $actions[] = [
                    'key' => 'journal_extraction',
                    'label' => 'Konversi skripsi ke naskah jurnal (IMRaD)',
                    'detail' => 'Naskah akhir siap dikonversi ke artikel jurnal ilmiah siap publikasi (PRD KPI #16).',
                    'url' => $workspace,
                    'severity' => 'info',
                ];
            }
        }

        // 8) Bisa lanjut ke tahap berikutnya.
        if ($record?->status === StageStatus::COMPLETED && $stage !== ProjectStage::FINAL) {
            $actions[] = [
                'key' => 'advance',
                'label' => 'Lanjut ke tahap '.($stage->next()?->label() ?? 'berikutnya'),
                'detail' => 'Tahap ini selesai. Lanjutkan ke tahap berikutnya.',
                'url' => $workspace,
                'severity' => 'info',
            ];
        }

        // 7) Semua beres.
        if ($actions === []) {
            $actions[] = [
                'key' => 'done',
                'label' => 'Semua berjalan baik',
                'detail' => 'Tidak ada tindakan mendesak. Lanjutkan menulis atau ekspor naskah.',
                'url' => $workspace,
                'severity' => 'pass',
            ];
        }

        return $actions;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

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

        // 5) Belum ada rekomendasi jurnal (relevan di tahap Proposal/Final).
        if ($recommendations === 0 && in_array($stage, [ProjectStage::PROPOSAL, ProjectStage::FINAL], true)) {
            $actions[] = [
                'key' => 'venue',
                'label' => 'Cocokkan venue jurnal',
                'detail' => 'Belum ada rekomendasi jurnal. Jalankan Venue Matcher.',
                'url' => '/admin/platform-matcher-page?project='.$project->id,
                'severity' => 'info',
            ];
        }

        // 6) Bisa lanjut ke tahap berikutnya.
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

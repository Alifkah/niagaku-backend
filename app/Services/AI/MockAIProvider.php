<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use Illuminate\Support\Str;

class MockAIProvider implements AIProviderInterface
{
    /**
     * Synthesize intelligent Bahasa Indonesia business advice based on structured tenant data
     */
    public function generateResponse(string $userPrompt, array $businessContext): string
    {
        $prompt = Str::lower($userPrompt);

        // 1. Profit / Laba Rugi intent
        if (Str::contains($prompt, ['profit', 'laba', 'untung', 'margin'])) {
            $netProfit = number_format($businessContext['dashboard']['net_profit'] ?? 0, 0, ',', '.');
            $grossProfit = number_format($businessContext['dashboard']['gross_profit'] ?? 0, 0, ',', '.');
            $expenses = number_format($businessContext['dashboard']['operating_expenses'] ?? 0, 0, ',', '.');
            $margin = $businessContext['dashboard']['net_margin_percent'] ?? 0;

            return "📊 **Ringkasan Performa Laba Rugi Usaha Anda:**\n\n" .
                "• **Laba Kotor (Gross Profit):** Rp{$grossProfit}\n" .
                "• **Total Pengeluaran:** Rp{$expenses}\n" .
                "• **Laba Bersih (Net Profit):** Rp{$netProfit} (Margin: **{$margin}%**)\n\n" .
                "💡 *Saran NiagaKu AI:* " .
                (($businessContext['dashboard']['net_profit'] ?? 0) >= 0
                    ? 'Performa keuangan usaha Anda dalam kondisi sehat dan menguntungkan! Pertahankan efisiensi biaya operasional.'
                    : 'Perhatikan tingginya pengeluaran operasional dibanding pendapatan untuk meningkatkan margin laba.');
        }

        // 2. Omzet / Revenue / Penjualan intent
        if (Str::contains($prompt, ['omzet', 'revenue', 'penjualan', 'masuk'])) {
            $revenue = number_format($businessContext['dashboard']['revenue'] ?? 0, 0, ',', '.');
            $sales = number_format($businessContext['dashboard']['total_sales'] ?? 0, 0, ',', '.');
            $activeOrders = $businessContext['dashboard']['active_orders_count'] ?? 0;

            return "💰 **Informasi Omzet & Penjualan:**\n\n" .
                "• **Pembayaran Lunas (Confirmed Revenue):** Rp{$revenue}\n" .
                "• **Total Transaksi Penjualan:** Rp{$sales}\n" .
                "• **Order Aktif Berjalan:** {$activeOrders} Pesanan\n\n" .
                "Lakukan tindak lanjut pada pesanan aktif untuk mempercepat arus kas lunas.";
        }

        // 3. Receivables / Piutang / Tagihan / Belum Lunas intent
        if (Str::contains($prompt, ['piutang', 'lunas', 'tagihan', 'utang', 'belum bayar'])) {
            $receivables = number_format($businessContext['receivables']['total_receivables'] ?? 0, 0, ',', '.');
            $overdue = number_format($businessContext['receivables']['overdue_amount'] ?? 0, 0, ',', '.');
            $customerCount = count($businessContext['receivables']['customer_breakdown'] ?? []);

            $text = "💳 **Status Piutang & Tagihan Pelanggan:**\n\n" .
                "• **Total Piutang Berjalan:** Rp{$receivables}\n" .
                "• **Piutang Jatuh Tempo (Overdue):** Rp{$overdue}\n" .
                "• **Jumlah Pelanggan Menunggak:** {$customerCount} Pelanggan\n\n";

            if ($customerCount > 0) {
                $text .= "⚠️ *Pelanggan dengan tagihan terbesar:*\n";
                foreach (array_slice($businessContext['receivables']['customer_breakdown'], 0, 3) as $c) {
                    $cName = $c['customer']['name'] ?? 'Pelanggan Umum';
                    $cAmount = number_format($c['outstanding_balance'] ?? 0, 0, ',', '.');
                    $text .= "- **{$cName}**: Rp{$cAmount}\n";
                }
            }

            return $text;
        }

        // 4. Pengeluaran / Biaya intent
        if (Str::contains($prompt, ['pengeluaran', 'biaya', 'biaya operasional', 'keluar'])) {
            $expenses = number_format($businessContext['expenses']['total_expenses'] ?? 0, 0, ',', '.');
            $text = "💸 **Rincian Pengeluaran Usaha:**\n\n" .
                "• **Total Pengeluaran:** Rp{$expenses}\n\n";

            if (!empty($businessContext['expenses']['category_breakdown'])) {
                $text .= "📂 *Kategori Pengeluaran Terbesar:*\n";
                foreach ($businessContext['expenses']['category_breakdown'] as $cat) {
                    $cName = $cat['category'];
                    $cAmount = number_format($cat['total_amount'], 0, ',', '.');
                    $cPct = $cat['percentage'];
                    $text .= "- **{$cName}**: Rp{$cAmount} ({$cPct}%)\n";
                }
            }

            return $text;
        }

        // 5. Default General Business Assistance Intent
        $revenue = number_format($businessContext['dashboard']['revenue'] ?? 0, 0, ',', '.');
        $netProfit = number_format($businessContext['dashboard']['net_profit'] ?? 0, 0, ',', '.');
        $receivables = number_format($businessContext['receivables']['total_receivables'] ?? 0, 0, ',', '.');

        return "🤖 **NiagaKu AI Business Assistant:**\n\n" .
            "Berikut ringkasan kilat kondisi bisnis Anda saat ini:\n" .
            "• **Omzet Lunas:** Rp{$revenue}\n" .
            "• **Estimasi Laba Bersih:** Rp{$netProfit}\n" .
            "• **Piutang Berjalan:** Rp{$receivables}\n\n" .
            "Ada yang bisa saya bantu lebih spesifik tentang penjualan, laba rugi, piutang, atau pengeluaran?";
    }
}

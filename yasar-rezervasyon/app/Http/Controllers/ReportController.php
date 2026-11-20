<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        return view('reports.index', $this->data($request, $reports));
    }

    public function export(Request $request, string $format, ReportService $reports)
    {
        $data = $this->data($request, $reports);
        Audit::log('report.exported', null, ['format' => $format, 'from' => $data['from']->toDateString(), 'to' => $data['to']->toDateString()]);
        $name = 'rapor-'.$data['from']->format('Ymd').'-'.$data['to']->format('Ymd');

        if ($format === 'pdf') {
            return Pdf::loadView('reports.pdf', $data)->setPaper('a4', 'landscape')->download($name.'.pdf');
        }

        $path = tempnam(sys_get_temp_dir(), 'rpt');
        $writer = new Writer;
        $writer->openToFile($path);
        $r = $data['report'];
        $sheet = function (string $title, array $header, array $rows, bool $first = false) use ($writer) {
            if (! $first) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $writer->getCurrentSheet()->setName(mb_substr($title, 0, 31));
            $writer->addRow(Row::fromValues($header));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_values($row)));
            }
        };
        $sheet(__('Doluluk'), [__('Tesis'), __('Rezervasyon'), __('Dolu saat'), __('Açık saat'), __('Doluluk %')], $r['occupancy'], true);
        $sheet(__('Roller'), [__('Rol'), __('Rezervasyon'), __('Kişi')], $r['byRole']);
        $sheet(__('En çok kullananlar'), [__('Ad'), __('Numara'), __('Rezervasyon')], $r['topUsers']);
        $sheet(__('Katılım'), [__('Gösterge'), __('Değer')], collect($r['attendance'])->map(fn ($v, $k) => [$k, $v])->values()->all());
        $sheet(__('Yaptırımlar'), [__('Ad'), __('Numara'), __('Başlangıç'), __('Bitiş'), __('Gerekçe'), __('Kaldırıldı')], $r['sanctioned']);
        $sheet(__('Onaycılar'), [__('Onaycı'), __('Onay'), __('Red')], $r['approval']['by_approver']);
        $sheet(__('Takımlar'), [__('Takım'), __('Rezervasyon'), __('Saat')], $r['teams']);
        $sheet(__('Ekipman'), [__('Kod'), __('Ekipman'), __('Adet'), __('Durum'), __('Kişi'), __('Not')], $r['equipment']);
        $writer->close();

        return response()->download($path, $name.'.xlsx')->deleteFileAfterSend();
    }

    private function data(Request $request, ReportService $reports): array
    {
        $from = rescue(fn () => Carbon::parse($request->query('from')), today()->subDays(30), false)->startOfDay();
        $to = rescue(fn () => Carbon::parse($request->query('to')), today(), false)->endOfDay();
        if ($from->gt($to) || $from->diffInDays($to) > 400) {
            $from = $to->copy()->subDays(30)->startOfDay();
        }
        $available = $reports->facilitiesFor($request->user());
        $facilities = $request->filled('facility') ? $available->where('id', (int) $request->query('facility'))->values() : $available;

        return [
            'from' => $from, 'to' => $to, 'facilities' => $available, 'selected' => $request->query('facility'),
            'report' => $reports->build($from, $to, $facilities),
        ];
    }
}

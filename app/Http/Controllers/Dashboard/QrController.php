<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Menus\Models\QrLink;
use App\Domain\Tenancy\Models\Branch;
use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;

class QrController extends Controller
{
    public function show(Branch $branch)
    {
        $this->authorizeBranch($branch);

        return view('dashboard.menu.qr', ['branch' => $branch, 'link' => QrLink::currentFor($branch)]);
    }

    public function svg(Branch $branch)
    {
        $this->authorizeBranch($branch);
        $writer = new Writer(new ImageRenderer(new RendererStyle(320, 2), new SvgImageBackEnd));

        return response($writer->writeString(QrLink::currentFor($branch)->url()), 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function rotate(Branch $branch): RedirectResponse
    {
        $this->authorizeBranch($branch);
        QrLink::rotate($branch);
        AuditLog::record('qr.rotated', $branch, [], $branch->business_id);

        return back()->with('status', 'A new link was issued. The old QR code no longer works.');
    }

    private function authorizeBranch(Branch $branch): void
    {
        $this->ensureTenant($branch);
        $this->authorize('managePricing', $branch);
    }
}

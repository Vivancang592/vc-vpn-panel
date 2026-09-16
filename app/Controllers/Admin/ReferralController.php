<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ReferralCommission;

class ReferralController extends BaseController
{
    private ReferralCommission $referralModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->referralModel = new ReferralCommission();
    }

    public function index(): void
    {
        $commissions = $this->referralModel->allWithDetails();

        $this->render('admin.referrals.index', [
            'activeMenu'  => 'referrals',
            'commissions' => $commissions
        ]);
    }
}
-- Migration: Update DECIMAL(15,2) to DECIMAL(15,0) for integer currency values
-- Created: 2026-09-22
-- Purpose: Remove .00 suffix from currency values (e.g., 1000 instead of 1000.00)

-- vc_users table
ALTER TABLE `vc_users`
    MODIFY COLUMN `balance` DECIMAL(15, 0) NOT NULL DEFAULT 0,
    MODIFY COLUMN `commission_balance` DECIMAL(15, 0) NOT NULL DEFAULT 0;

-- vc_vpn_plans table
ALTER TABLE `vc_vpn_plans`
    MODIFY COLUMN `price` DECIMAL(15, 0) NOT NULL;

-- vc_coupons table
ALTER TABLE `vc_coupons`
    MODIFY COLUMN `discount_value` DECIMAL(15, 0) NOT NULL;

-- vc_orders table
ALTER TABLE `vc_orders`
    MODIFY COLUMN `total_amount` DECIMAL(15, 0) NOT NULL;

-- vc_payments table
ALTER TABLE `vc_payments`
    MODIFY COLUMN `amount` DECIMAL(15, 0) NOT NULL;

-- vc_referral_commissions table
ALTER TABLE `vc_referral_commissions`
    MODIFY COLUMN `commission_amount` DECIMAL(15, 0) NOT NULL;

-- vc_withdrawals table
ALTER TABLE `vc_withdrawals`
    MODIFY COLUMN `amount` DECIMAL(15, 0) NOT NULL;

-- vc_expenses table
ALTER TABLE `vc_expenses`
    MODIFY COLUMN `amount` DECIMAL(15, 0) NOT NULL;

-- Note: commission_rate in vc_referral_commissions stays as DECIMAL(5,2) for percentage values
ALTER TABLE `pa_refund_record`
  DROP INDEX `uk_refund_record_tenant_order`,
  DROP INDEX `uk_refund_record_order_global`,
  ADD KEY `idx_refund_record_tenant_order_amount`
    (`tenant_id`, `order_type`, `order_id`, `refund_amount`, `id`);

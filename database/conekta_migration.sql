-- Migración: agregar columnas de Conekta a la tabla pagos
ALTER TABLE pagos
  ADD COLUMN conekta_order_id    VARCHAR(100)  NULL AFTER estatus,
  ADD COLUMN conekta_customer_id VARCHAR(100)  NULL AFTER conekta_order_id,
  ADD COLUMN currency            VARCHAR(10)   NOT NULL DEFAULT 'MXN' AFTER conekta_customer_id,
  ADD COLUMN payment_method_type VARCHAR(50)   NULL AFTER currency,
  ADD COLUMN last_webhook_event  VARCHAR(100)  NULL AFTER payment_method_type,
  ADD COLUMN raw_response        JSON          NULL AFTER last_webhook_event;

-- Índice para buscar rápido por conekta_order_id (lo usa el webhook)
ALTER TABLE pagos
  ADD INDEX idx_conekta_order_id (conekta_order_id);

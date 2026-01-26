DROP PROCEDURE IF EXISTS sp_cancelar_pedido;
DELIMITER $$

CREATE PROCEDURE sp_cancelar_pedido(
    IN p_pedido_id INT
)
BEGIN
    DECLARE v_estatus VARCHAR(40);

    -- Manejo de errores: si algo falla, hace rollback
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- Bloquea el registro para evitar condiciones de carrera (FOR UPDATE)
    SELECT estatus
      INTO v_estatus
      FROM pedidos
     WHERE id = p_pedido_id
     FOR UPDATE;

    -- Validar que el pedido existe
    IF v_estatus IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El pedido no existe.';
    END IF;

    -- Validar que no esté ya cancelado
    IF UPPER(v_estatus) = 'CANCELADO' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El pedido ya está cancelado.';
    END IF;

    -- Restaurar el stock en inventario
    UPDATE inventario i
    JOIN pedido_productos pp ON pp.producto_id = i.producto_id
     SET i.cantidad_disponible = i.cantidad_disponible + pp.cantidad
   WHERE pp.pedido_id = p_pedido_id;

    -- Marcar pedido como cancelado
    UPDATE pedidos
       SET estatus = 'CANCELADO'
     WHERE id = p_pedido_id;

    COMMIT;
END$$

DELIMITER ;
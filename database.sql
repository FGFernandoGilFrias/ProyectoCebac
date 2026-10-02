USE cebac;

-- 1) Índice UNIQUE que necesita resultado_formulario.php para el ON DUPLICATE KEY UPDATE
ALTER TABLE resultados
  ADD UNIQUE KEY uk_resultados_orden_estudio (orden_estudio_id);

-- 2) Reemplazar hash del admin por uno válido (contraseña: Admin1234!)
UPDATE usuarios
SET hash_contrasena = '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS4bZJ18JsVNlIJP1o0xHfM7iCOW'
WHERE nombre_usuario = 'admin';

-- 3) Verificar que quedó bien
SELECT nombre_usuario, LEFT(hash_contrasena, 7) AS hash_inicio FROM usuarios WHERE nombre_usuario = 'admin';
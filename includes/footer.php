<?php
/**
 * includes/footer.php
 * Cierre común de las páginas.
 */
?>
</main>

<!-- Bootstrap 5.3 JS Bundle (incluye Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- JS propio -->
<?php
$jsPath = __DIR__ . '/../public/assets/app.js';
$jsVersion = file_exists($jsPath) ? filemtime($jsPath) : time();
?>
<script src="public/assets/app.js?v=<?= $jsVersion ?>"></script>

</body>
</html>
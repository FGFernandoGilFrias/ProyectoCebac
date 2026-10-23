<?php
session_start();

// Si ya está logueado, ir directo al sistema
if (isset($_SESSION['usuario'])) {
    header('Location: ordenes.php');
    exit;
}

$tituloPagina = 'Sistema de Gestión para Laboratorios Clínicos';
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Sistema de gestión integral para laboratorios clínicos. Pacientes, órdenes, muestras, resultados, obras sociales y facturación.">
    <title><?= htmlspecialchars($tituloPagina) ?></title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- CSS propio -->
    <link rel="stylesheet" href="public/assets/app.css">

    <!-- Open Graph (para compartir) -->
    <meta property="og:title" content="Sistema de Gestión para Laboratorios Clínicos">
    <meta property="og:description" content="Centralizá pacientes, órdenes, muestras, resultados y facturación en una sola plataforma.">
    <meta property="og:type" content="website">
</head>
<body class="landing-page">

<!-- ==========================================================
     NAVBAR (sin marca)
     ========================================================== -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-droplet-fill text-primary fs-3"></i>
            <span class="fw-bold text-dark">Sistema de Gestión</span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#menuLanding" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-end" id="menuLanding">
            <ul class="navbar-nav align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="#caracteristicas">Características</a></li>
                <li class="nav-item"><a class="nav-link" href="#como-funciona">Cómo funciona</a></li>
                <li class="nav-item"><a class="nav-link" href="#beneficios">Beneficios</a></li>
                <li class="nav-item"><a class="nav-link" href="#contacto">Contacto</a></li>
                <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                    <a class="btn btn-primary" href="login.php">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ==========================================================
     HERO
     ========================================================== -->
<section class="landing-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2">
                    <i class="bi bi-stars me-1"></i>Sistema de gestión integral
                </span>
                <h1 class="display-4 fw-bold mb-3">
                    Gestión inteligente para tu <span class="text-primary">laboratorio clínico</span>
                </h1>
                <p class="lead text-muted mb-4">
                    Centralizá pacientes, órdenes médicas, muestras, resultados, obras sociales y facturación
                    en una sola plataforma. Optimizá tu laboratorio con tecnología moderna.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="login.php" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión
                    </a>
                    <a href="#caracteristicas" class="btn btn-outline-primary btn-lg px-4">
                        <i class="bi bi-info-circle me-2"></i>Ver características
                    </a>
                </div>

                <div class="landing-hero-badges mt-4">
                    <span class="badge bg-light text-dark border me-2 mb-2">
                        <i class="bi bi-check-circle-fill text-success me-1"></i>100% digital
                    </span>
                    <span class="badge bg-light text-dark border me-2 mb-2">
                        <i class="bi bi-check-circle-fill text-success me-1"></i>Responsive
                    </span>
                    <span class="badge bg-light text-dark border mb-2">
                        <i class="bi bi-check-circle-fill text-success me-1"></i>Envío automático
                    </span>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="landing-hero-visual">
                    <div class="landing-hero-icon">
                        <i class="bi bi-droplet-fill"></i>
                    </div>
                    <div class="landing-hero-pulse"></div>
                    <div class="landing-hero-pulse delay-1"></div>
                    <div class="landing-hero-pulse delay-2"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================
     CARACTERÍSTICAS
     ========================================================== -->
<section id="caracteristicas" class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-primary bg-opacity-10 text-primary mb-2 px-3 py-2">Módulos</span>
            <h2 class="fw-bold">¿Qué incluye el sistema?</h2>
            <p class="text-muted">Todas las herramientas que tu laboratorio necesita</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4 landing-feature">
                    <div class="feature-icon bg-primary bg-opacity-10 text-primary mx-auto mb-3">
                        <i class="bi bi-people fs-2"></i>
                    </div>
                    <h5 class="fw-bold">Pacientes</h5>
                    <p class="text-muted small mb-0">Ficha completa con obra social, credencial y cobertura.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4 landing-feature">
                    <div class="feature-icon bg-success bg-opacity-10 text-success mx-auto mb-3">
                        <i class="bi bi-file-earmark-medical fs-2"></i>
                    </div>
                    <h5 class="fw-bold">Órdenes médicas</h5>
                    <p class="text-muted small mb-0">Carga rápida con cálculo automático de cobertura.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4 landing-feature">
                    <div class="feature-icon bg-warning bg-opacity-10 text-warning mx-auto mb-3">
                        <i class="bi bi-clipboard-check fs-2"></i>
                    </div>
                    <h5 class="fw-bold">Muestras</h5>
                    <p class="text-muted small mb-0">Check-in con validación y control de rechazos.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4 landing-feature">
                    <div class="feature-icon bg-info bg-opacity-10 text-info mx-auto mb-3">
                        <i class="bi bi-file-earmark-text fs-2"></i>
                    </div>
                    <h5 class="fw-bold">Resultados</h5>
                    <p class="text-muted small mb-0">Carga, validación y envío automático por email.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4 landing-feature">
                    <div class="feature-icon bg-danger bg-opacity-10 text-danger mx-auto mb-3">
                        <i class="bi bi-shield-check fs-2"></i>
                    </div>
                    <h5 class="fw-bold">Obras sociales</h5>
                    <p class="text-muted small mb-0">Convenios, credenciales y coberturas.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4 landing-feature">
                    <div class="feature-icon bg-success bg-opacity-10 text-success mx-auto mb-3">
                        <i class="bi bi-cash-coin fs-2"></i>
                    </div>
                    <h5 class="fw-bold">Caja</h5>
                    <p class="text-muted small mb-0">Pagos, saldos y comprobantes automáticos.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4 landing-feature">
                    <div class="feature-icon bg-primary bg-opacity-10 text-primary mx-auto mb-3">
                        <i class="bi bi-bar-chart fs-2"></i>
                    </div>
                    <h5 class="fw-bold">Reportes</h5>
                    <p class="text-muted small mb-0">Estudios, rechazos, liquidación mensual y más.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4 landing-feature">
                    <div class="feature-icon bg-info bg-opacity-10 text-info mx-auto mb-3">
                        <i class="bi bi-printer fs-2"></i>
                    </div>
                    <h5 class="fw-bold">Impresión</h5>
                    <p class="text-muted small mb-0">Etiquetas, instrucciones, comprobantes y PDFs.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================
     ¿CÓMO FUNCIONA?
     ========================================================== -->
<section id="como-funciona" class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-primary bg-opacity-10 text-primary mb-2 px-3 py-2">Flujo de trabajo</span>
            <h2 class="fw-bold">¿Cómo funciona?</h2>
            <p class="text-muted">El sistema acompaña todo el proceso del laboratorio</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="landing-step text-center">
                    <div class="step-number">1</div>
                    <div class="step-icon">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <h6 class="fw-bold mt-3">Alta del paciente</h6>
                    <p class="text-muted small mb-0">Cargás los datos personales y su obra social.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="landing-step text-center">
                    <div class="step-number">2</div>
                    <div class="step-icon">
                        <i class="bi bi-file-earmark-plus"></i>
                    </div>
                    <h6 class="fw-bold mt-3">Nueva orden</h6>
                    <p class="text-muted small mb-0">Seleccionás los estudios y se calcula la cobertura.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="landing-step text-center">
                    <div class="step-number">3</div>
                    <div class="step-icon">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <h6 class="fw-bold mt-3">Check-in de muestras</h6>
                    <p class="text-muted small mb-0">El técnico valida las muestras recibidas.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="landing-step text-center">
                    <div class="step-number">4</div>
                    <div class="step-icon">
                        <i class="bi bi-send-check"></i>
                    </div>
                    <h6 class="fw-bold mt-3">Resultados</h6>
                    <p class="text-muted small mb-0">Se cargan y se envían automáticamente al paciente.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================
     NÚMEROS
     ========================================================== -->
<section class="landing-numbers py-5">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-6 col-md-3">
                <div class="landing-number">
                    <div class="number-value">8</div>
                    <div class="number-label">Módulos integrados</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="landing-number">
                    <div class="number-value">100%</div>
                    <div class="number-label">Digital y sin papel</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="landing-number">
                    <div class="number-value">24/7</div>
                    <div class="number-label">Acceso online</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="landing-number">
                    <div class="number-value">∞</div>
                    <div class="number-label">Pacientes ilimitados</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================
     BENEFICIOS
     ========================================================== -->
<section id="beneficios" class="py-5 bg-light">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <span class="badge bg-primary bg-opacity-10 text-primary mb-2 px-3 py-2">Ventajas</span>
                <h2 class="fw-bold mb-4">Beneficios del sistema</h2>

                <div class="landing-benefit">
                    <div class="text-success fs-4"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">Gestión centralizada</h6>
                        <p class="text-muted small mb-0">Todos los datos del laboratorio en un solo lugar.</p>
                    </div>
                </div>

                <div class="landing-benefit">
                    <div class="text-success fs-4"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">Automatización de procesos</h6>
                        <p class="text-muted small mb-0">Envío de resultados, PDFs y notificaciones.</p>
                    </div>
                </div>

                <div class="landing-benefit">
                    <div class="text-success fs-4"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">Facturación a obras sociales</h6>
                        <p class="text-muted small mb-0">Liquidación mensual automática.</p>
                    </div>
                </div>

                <div class="landing-benefit">
                    <div class="text-success fs-4"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">Control de muestras</h6>
                        <p class="text-muted small mb-0">Check-in, validación y segundo análisis.</p>
                    </div>
                </div>

                <div class="landing-benefit">
                    <div class="text-success fs-4"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">Acceso desde cualquier dispositivo</h6>
                        <p class="text-muted small mb-0">Diseño responsive para PC, tablet y celular.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="landing-cta-card">
                    <div class="landing-cta-icon mb-3">
                        <i class="bi bi-rocket-takeoff"></i>
                    </div>
                    <h3 class="fw-bold mb-3 text-white">¿Listo para empezar?</h3>
                    <p class="mb-4 text-white-50">
                        Accedé al sistema con tus credenciales y comenzá a gestionar tu laboratorio.
                    </p>
                    <a href="login.php" class="btn btn-light btn-lg w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión
                    </a>
                    <p class="text-white-50 small text-center mt-3 mb-0">
                        ¿No tenés cuenta? Contactanos.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================
     CTA FINAL
     ========================================================== -->
<section class="landing-final-cta py-5">
    <div class="container text-center">
        <h2 class="fw-bold mb-3">Optimizá tu laboratorio hoy</h2>
        <p class="text-muted mb-4 mx-auto" style="max-width: 600px;">
            Sumate a la transformación digital del laboratorio clínico. Todos los procesos en un solo sistema.
        </p>
        <a href="login.php" class="btn btn-primary btn-lg px-5">
            <i class="bi bi-box-arrow-in-right me-2"></i>Acceder al sistema
        </a>
    </div>
</section>

<!-- ==========================================================
     FOOTER (con contacto del laboratorio, sin marca del sistema)
     ========================================================== -->
<footer id="contacto" class="landing-footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-droplet-fill text-primary fs-3"></i>
                    <span class="fw-bold text-white fs-4">Sistema de Gestión</span>
                </div>
                <p class="text-white-50 small mb-0">
                    Sistema de gestión integral para laboratorios clínicos.
                </p>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <h6 class="fw-bold mb-3 text-white">Contacto</h6>
                <p class="small mb-1 text-white-50"><i class="bi bi-geo-alt me-2"></i>Córdoba 1393 - Posadas - Misiones</p>
                <p class="small mb-1 text-white-50"><i class="bi bi-telephone me-2"></i>3764 20 59 95</p>
                <p class="small mb-0 text-white-50"><i class="bi bi-envelope me-2"></i>mantenimientocebac@gmail.com</p>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold mb-3 text-white">Acceso</h6>
                <a href="login.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión
                </a>
            </div>
        </div>
        <hr class="border-secondary my-4">
        <p class="text-center text-white-50 small mb-0">
            Sistema de Gestión © <?= date('Y') ?> · Todos los derechos reservados
        </p>
    </div>
</footer>

<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Smooth scroll para los enlaces internos
document.querySelectorAll('a[href^="#"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            const offset = 70;
            const y = target.getBoundingClientRect().top + window.pageYOffset - offset;
            window.scrollTo({ top: y, behavior: 'smooth' });

            // Cerrar el menú mobile si está abierto
            const navCollapse = document.getElementById('menuLanding');
            if (navCollapse && navCollapse.classList.contains('show')) {
                bootstrap.Collapse.getInstance(navCollapse)?.hide();
            }
        }
    });
});
</script>

</body>
</html>
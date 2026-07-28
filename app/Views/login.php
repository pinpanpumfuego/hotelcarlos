<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · <?= esc(config('Hotel')->nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(160deg, #1a3c5e 0%, #2e5f8a 60%, #3d7ab0 100%);
        }
        .tarjeta-login { max-width: 420px; width: 100%; border-radius: 1rem; }
        .logo-hotel {
            width: 64px; height: 64px; border-radius: 50%;
            background: #1a3c5e; color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="card tarjeta-login shadow-lg border-0 m-3">
        <div class="card-body p-4 p-md-5">
            <div class="logo-hotel mb-3"><i class="bi bi-tree"></i></div>
            <h1 class="h4 text-center mb-1"><?= esc(config('Hotel')->nombre) ?></h1>
            <p class="text-muted text-center mb-4">Sistema de gestión hotelera</p>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif ?>
            <?php if (session()->getFlashdata('ok')): ?>
                <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= esc(session()->getFlashdata('ok')) ?></div>
            <?php endif ?>

            <?php // La pestaña del PIN solo aparece en equipos ya reconocidos:
                  // en uno nuevo sería un formulario que nunca deja pasar. ?>
            <?php if (! empty($equipoReconocido)): ?>
                <ul class="nav nav-pills nav-fill mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#por-pin"
                                type="button" role="tab">
                            <i class="bi bi-123 me-1"></i>PIN
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#por-clave"
                                type="button" role="tab">
                            <i class="bi bi-key me-1"></i>Contraseña
                        </button>
                    </li>
                </ul>
            <?php endif ?>

            <div class="tab-content">
                <?php if (! empty($equipoReconocido)): ?>
                    <div class="tab-pane fade show active" id="por-pin" role="tabpanel">
                        <form method="post" action="<?= site_url('login/pin') ?>">
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label class="form-label">Correo electrónico</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" required autofocus
                                           autocomplete="username" value="<?= esc(old('email', '')) ?>">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">PIN</label>
                                <input type="password" name="pin" class="form-control form-control-lg text-center"
                                       inputmode="numeric" pattern="[0-9]*" maxlength="4" required
                                       autocomplete="off" style="letter-spacing: .8em; font-size: 1.6rem;">
                                <div class="form-text">
                                    El mismo de fichar. Con el PIN se entra a trabajar; para cobrar o
                                    ver documentos de huéspedes se pide la contraseña.
                                </div>
                            </div>

                            <button class="btn btn-primary w-100 py-2">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
                            </button>
                        </form>
                    </div>
                <?php endif ?>

                <div class="tab-pane fade <?= empty($equipoReconocido) ? 'show active' : '' ?>" id="por-clave" role="tabpanel">
                    <form method="post" action="<?= site_url('login/entrar') ?>">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label">Correo electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control" required
                                       <?= empty($equipoReconocido) ? 'autofocus' : '' ?>
                                       autocomplete="username" value="<?= esc(old('email', '')) ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="clave" class="form-control" required autocomplete="current-password">
                            </div>
                        </div>

                        <button class="btn btn-primary w-100 py-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
                        </button>

                        <?php if (empty($equipoReconocido)): ?>
                            <p class="form-text text-center mt-3 mb-0">
                                Entra una vez con tu contraseña y este equipo quedará reconocido:
                                a partir de la próxima te bastará el PIN.
                            </p>
                        <?php endif ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

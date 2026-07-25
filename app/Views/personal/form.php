<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $editando = isset($empleado['id']); ?>

<div class="mb-4">
    <a href="<?= $editando ? site_url('personal/ver/' . $empleado['id']) : site_url('personal') ?>"
       class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i><?= $editando ? 'Ficha del empleado' : 'Personal' ?>
    </a>
    <h1 class="h3 mb-0 mt-1"><?= $editando ? 'Editar ficha' : 'Nuevo empleado' ?></h1>
</div>

<?= view('partes/errores') ?>

<form method="post" action="<?= $editando ? site_url('personal/actualizar/' . $empleado['id']) : site_url('personal/guardar') ?>">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- ═══ Datos personales ═══ -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-2"></i>Datos personales</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" required
                                   value="<?= esc(old('nombre', $empleado['nombre'] ?? '')) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" name="apellidos" class="form-control" required
                                   value="<?= esc(old('apellidos', $empleado['apellidos'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-5">
                            <label class="form-label">Tipo de documento</label>
                            <select name="tipo_documento" class="form-select">
                                <?php foreach ($tiposDoc as $valor => $etiqueta): ?>
                                    <option value="<?= $valor ?>" <?= old('tipo_documento', $empleado['tipo_documento'] ?? 'CC') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Número <span class="text-danger">*</span></label>
                            <input type="text" name="num_documento" class="form-control" required
                                   value="<?= esc(old('num_documento', $empleado['num_documento'] ?? '')) ?>">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label">Nacimiento</label>
                            <input type="date" name="fecha_nacimiento" class="form-control"
                                   value="<?= esc(old('fecha_nacimiento', $empleado['fecha_nacimiento'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="telefono" class="form-control"
                                   value="<?= esc(old('telefono', $empleado['telefono'] ?? '')) ?>" placeholder="+57 300 000 0000">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Correo</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= esc(old('email', $empleado['email'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-7">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="direccion" class="form-control"
                                   value="<?= esc(old('direccion', $empleado['direccion'] ?? '')) ?>">
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label">Ciudad</label>
                            <input type="text" name="ciudad" class="form-control"
                                   value="<?= esc(old('ciudad', $empleado['ciudad'] ?? '')) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Datos laborales ═══ -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-briefcase me-2"></i>Datos laborales</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-7">
                            <label class="form-label">Cargo <span class="text-danger">*</span></label>
                            <input type="text" name="cargo" class="form-control" required
                                   value="<?= esc(old('cargo', $empleado['cargo'] ?? '')) ?>"
                                   placeholder="Recepcionista, camarera, cocinero…">
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label">Área</label>
                            <select name="area" class="form-select">
                                <?php foreach ($areas as $valor => $etiqueta): ?>
                                    <option value="<?= $valor ?>" <?= old('area', $empleado['area'] ?? 'otro') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Tipo de contrato</label>
                            <select name="tipo_contrato" class="form-select">
                                <?php foreach ($contratos as $valor => $etiqueta): ?>
                                    <option value="<?= $valor ?>" <?= old('tipo_contrato', $empleado['tipo_contrato'] ?? 'indefinido') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Jornada</label>
                            <input type="text" name="jornada" class="form-control"
                                   value="<?= esc(old('jornada', $empleado['jornada'] ?? '')) ?>" placeholder="Tiempo completo, medio tiempo…">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label">Ingreso</label>
                            <input type="date" name="fecha_ingreso" class="form-control"
                                   value="<?= esc(old('fecha_ingreso', $empleado['fecha_ingreso'] ?? '')) ?>">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Salida</label>
                            <input type="date" name="fecha_salida" class="form-control"
                                   value="<?= esc(old('fecha_salida', $empleado['fecha_salida'] ?? '')) ?>">
                            <div class="form-text">Solo si ya no trabaja aquí.</div>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Salario mensual</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="salario" class="form-control" min="0" step="1000"
                                       value="<?= esc(old('salario', (int) ($empleado['salario'] ?? 0))) ?>">
                            </div>
                        </div>
                    </div>

                    <label class="form-label">Cuenta de acceso al sistema</label>
                    <select name="usuario_id" class="form-select">
                        <option value="">Sin cuenta — no entra al sistema</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= esc($u['id']) ?>" <?= (string) old('usuario_id', $empleado['usuario_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>>
                                <?= esc($u['nombre']) ?> — <?= esc($u['email']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <div class="form-text">
                        Vincula esta ficha con su usuario para saber quién hace cada cosa.
                        Las cuentas se crean en <a href="<?= site_url('usuarios') ?>">Usuarios</a>.
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="chkActivo"
                               <?= old('activo', $empleado['activo'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chkActivo">
                            <strong>En plantilla</strong>
                            <span class="d-block small text-muted">Solo el personal en plantilla aparece en turnos y ausencias.</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Seguridad social ═══ -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-shield-plus me-2"></i>Seguridad social y pago</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">EPS (salud)</label>
                            <input type="text" name="eps" class="form-control"
                                   value="<?= esc(old('eps', $empleado['eps'] ?? '')) ?>" placeholder="Sura, Sanitas, Nueva EPS…">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">ARL (riesgos laborales)</label>
                            <input type="text" name="arl" class="form-control"
                                   value="<?= esc(old('arl', $empleado['arl'] ?? '')) ?>" placeholder="Positiva, Sura, Colmena…">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Fondo de pensiones</label>
                            <input type="text" name="fondo_pension" class="form-control"
                                   value="<?= esc(old('fondo_pension', $empleado['fondo_pension'] ?? '')) ?>" placeholder="Porvenir, Protección…">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Caja de compensación</label>
                            <input type="text" name="caja_compensacion" class="form-control"
                                   value="<?= esc(old('caja_compensacion', $empleado['caja_compensacion'] ?? '')) ?>" placeholder="Comfama, Comfenalco…">
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-5">
                            <label class="form-label">Banco</label>
                            <input type="text" name="banco" class="form-control"
                                   value="<?= esc(old('banco', $empleado['banco'] ?? '')) ?>">
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label">Cuenta para el pago</label>
                            <input type="text" name="cuenta_bancaria" class="form-control"
                                   value="<?= esc(old('cuenta_bancaria', $empleado['cuenta_bancaria'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="alert alert-light border mt-3 mb-0 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Estos datos son de consulta para el hotel. La <strong>liquidación de nómina</strong>
                        y los aportes se hacen en tu software contable: este sistema no los calcula.
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Emergencia y notas ═══ -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-telephone-plus me-2"></i>Contacto de emergencia</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="emergencia_nombre" class="form-control"
                                   value="<?= esc(old('emergencia_nombre', $empleado['emergencia_nombre'] ?? '')) ?>">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="emergencia_telefono" class="form-control"
                                   value="<?= esc(old('emergencia_telefono', $empleado['emergencia_telefono'] ?? '')) ?>">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label">Parentesco</label>
                            <input type="text" name="emergencia_parentesco" class="form-control"
                                   value="<?= esc(old('emergencia_parentesco', $empleado['emergencia_parentesco'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="form-text mb-3">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Importante en un ecolodge de montaña: si hay un accidente, es a quien se llama.
                    </div>

                    <label class="form-label">Notas internas</label>
                    <textarea name="notas" class="form-control" rows="4"
                              placeholder="Formación, idiomas, particularidades…"><?= esc(old('notas', $empleado['notas'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Guardar ficha</button>
        <a href="<?= $editando ? site_url('personal/ver/' . $empleado['id']) : site_url('personal') ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?= $this->endSection() ?>

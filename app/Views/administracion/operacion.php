<?= $this->extend("layouts/panel") ?>
<?= $this->section("contenido") ?>

<h1 class="h3 mb-1">Administración</h1>
<p class="text-muted">Lo que ajusta quien lleva el d\xc3\xada a d\xc3\xada: TPV, jornada, gesto verde y mantenimiento.</p>

<?= view("administracion/_nav", ["activa" => "operacion"]) ?>

<?= view("partes/errores") ?>

<div class="row g-4">
    <div class="col-lg-6" id="tpv">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-people me-2 text-primary"></i>TPV compartido
            </div>
            <form method="post" action="<?= site_url('administracion/tpv') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="compartido" id="tpvCompartido"
                               <?= $tpv['compartido'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tpvCompartido">
                            <strong>Cada camarero se identifica</strong>
                            <span class="d-block form-text">
                                La pantalla se bloquea y cada uno abre con su PIN de fichaje o su tarjeta.
                                Todo lo que haga queda a su nombre. Apágalo si solo lo usa una persona.
                            </span>
                        </label>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Se bloquea tras</label>
                            <div class="input-group">
                                <input type="number" name="bloqueo_seg" class="form-control" min="15" max="3600" step="15"
                                       value="<?= (int) $tpv['bloqueo_seg'] ?>">
                                <span class="input-group-text">seg</span>
                            </div>
                            <div class="form-text">Sin tocar la pantalla. También se bloquea al cobrar.</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Descuento sin permiso</label>
                            <div class="input-group">
                                <input type="number" name="descuento_libre" class="form-control" min="0" max="100" step="1"
                                       value="<?= (float) $tpv['descuento_libre'] ?>">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Por encima lo autoriza un encargado.</div>
                        </div>
                    </div>

                    <p class="form-text mb-3">
                        <strong>Anular una comanda siempre</strong> pide el PIN de un encargado, y queda
                        anotado quién lo autorizó.
                    </p>

                    <hr>
                    <label class="form-label fw-semibold">Propina sugerida</label>
                    <div class="input-group mb-2" style="max-width: 180px;">
                        <input type="number" name="propina_sugerida" class="form-control" min="0" max="100" step="1"
                               value="<?= (float) $tpv['propina_sugerida'] ?>">
                        <span class="input-group-text">%</span>
                    </div>
                    <p class="form-text mb-0">
                        El TPV ofrecerá <strong>Sin propina</strong>, la mitad y este porcentaje.
                        En Colombia la propina es <strong>voluntaria</strong> (Ley 1935 de 2018):
                        el cliente puede rechazarla o cambiarla, hay que decírselo por escrito
                        —el ticket ya lo hace— y el 100 % es de los trabajadores.
                        Pon 0 para no sugerir ninguna.
                    </p>

                    <hr>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="cocina_voz"
                               name="cocina_voz" <?= $tpv['cocina_voz'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="cocina_voz">
                            Leer las comandas en voz alta en cocina
                        </label>
                    </div>
                    <p class="form-text mb-0">
                        Al entrar una comanda, la pantalla de cocina la canta:
                        <em>«Nueva comanda. Mesa 3: dos de trucha al ajillo, sancocho de gallina.»</em>
                        Usa la voz del propio navegador, así que <strong>no cuesta nada y funciona
                        sin internet</strong>. Si nadie la marca como recibida, la repite al minuto
                        y medio. Cada pantalla puede silenciarla por su cuenta con el botón del
                        altavoz; este interruptor la apaga en todas.
                    </p>

                    <div class="form-check form-switch mt-3 mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="cocina_escucha"
                               name="cocina_escucha" <?= $tpv['cocina_escucha'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="cocina_escucha">
                            Que el cocinero pueda decir «oído cocina»
                        </label>
                    </div>
                    <div class="alert alert-warning py-2 small mb-0">
                        <p class="mb-2">
                            Con esto encendido, el cocinero marca la comanda como recibida
                            <strong>sin tocar la pantalla</strong>: basta con decir
                            <em>«oído cocina»</em> (también valen «recibido», «marcha» o «anotado»).
                            Con <em>«oído todo»</em> marca todas las que estén esperando.
                        </p>
                        <p class="mb-2">
                            <i class="bi bi-wifi me-1"></i>
                            <strong>Esto sí necesita internet</strong>, al revés que la lectura en voz
                            alta: Chrome envía el audio a Google para entenderlo. Sin conexión, el
                            botón «Recibido» sigue funcionando igual.
                        </p>
                        <p class="mb-0">
                            <i class="bi bi-mic me-1"></i>
                            <strong>El micrófono queda encendido durante el turno.</strong> Adviértelo
                            al personal antes de activarlo. Cada pantalla tiene además su propio
                            botón para apagarlo, y hay que dar permiso en el navegador la primera vez.
                        </p>
                    </div>

                    <?php if ($tpv['con_rol'] === []): ?>
                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>Nadie tiene acceso al TPV todavía.</strong> Si enciendes el modo compartido,
                            la pantalla quedará bloqueada sin que nadie pueda abrirla. Asigna el rol y el PIN
                            en <a href="<?= site_url('personal') ?>">las fichas del personal</a>.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light py-2 small mb-0">
                            <i class="bi bi-person-check me-1"></i>
                            Con acceso:
                            <?php foreach ($tpv['con_rol'] as $i => $e): ?>
                                <?= esc($e['nombre']) ?><?= $e['rol_tpv'] === 'encargado' ? ' <span class="badge text-bg-primary">encargado</span>' : '' ?><?= $i < count($tpv['con_rol']) - 1 ? ', ' : '' ?>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
                <div class="card-footer bg-white p-3">
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-6" id="fichaje">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-clock-history me-2 text-primary"></i>Control de jornada
            </div>
            <form method="post" action="<?= site_url('administracion/fichaje') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="terminal" id="fTerminal"
                               <?= $fichaje['terminal'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fTerminal">
                            <strong>Terminal encendido</strong>
                            <span class="d-block form-text">
                                La pantalla de <a href="<?= site_url('fichar') ?>" target="_blank" rel="noopener">fichar</a>
                                que se deja en recepción. Apágalo si no lo usas.
                            </span>
                        </label>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="foto" id="fFoto"
                               <?= $fichaje['foto'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fFoto">
                            <strong>Tomar foto al fichar en el terminal</strong>
                            <span class="d-block form-text">
                                Disuade de fichar por un compañero. Si la cámara falla, el fichaje se registra
                                igual pero queda señalado. Avísale al personal: es un dato personal suyo.
                            </span>
                        </label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="movil" id="fMovil"
                               <?= $fichaje['movil'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fMovil">
                            <strong>Permitir fichar desde el móvil</strong>
                            <span class="d-block form-text">
                                Con el portal del empleado. Puedes quitárselo a alguien en concreto desde su ficha.
                            </span>
                        </label>
                    </div>

                    <hr>
                    <p class="form-text mb-2">
                        <strong>Ubicación del hotel.</strong> Si la pones, cada fichaje desde el móvil anota
                        a cuántos metros se hizo. Sácala de Google Maps: pulsa largo sobre el punto y copia las
                        dos cifras que aparecen.
                    </p>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small mb-1">Latitud</label>
                            <input type="text" name="latitud" class="form-control form-control-sm"
                                   value="<?= esc($fichaje['latitud']) ?>" placeholder="6.1234567">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1">Longitud</label>
                            <input type="text" name="longitud" class="form-control form-control-sm"
                                   value="<?= esc($fichaje['longitud']) ?>" placeholder="-75.1234567">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1">Radio</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="radio" class="form-control" min="0" step="50"
                                       value="<?= (int) $fichaje['radio'] ?>">
                                <span class="input-group-text">m</span>
                            </div>
                        </div>
                    </div>
                    <p class="form-text mb-0">
                        Fichar fuera del radio <strong>no se bloquea</strong>: se anota y lo ves en el
                        <a href="<?= site_url('fichajes') ?>">control de jornada</a>. Puede haber mala señal,
                        o alguien haciendo de verdad un recado del hotel.
                    </p>
                </div>
                <div class="card-footer bg-white p-3">
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm" id="verde">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-leaf me-2 text-success"></i>Gesto verde
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    El huésped renuncia al cambio de toallas y sábanas de una noche, y el hotel le
                    invita a algo. Se ahorra una lavada entera —agua, energía, detergente y el rato
                    de una persona— y se paga una consumición.
                </p>

                <form method="post" action="<?= site_url('administracion/verde') ?>" class="row g-3">
                    <?= csrf_field() ?>

                    <div class="col-md-6">
                        <label class="form-label">¿De qué puede elegir?</label>
                        <select name="categoria_id" class="form-select">
                            <option value="0">— sin elegir —</option>
                            <?php foreach ($verde['categorias'] as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $verde['categoria_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= esc($c['nombre']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text">
                            Una categoría de la carta. <strong>Que no lleve alcohol</strong>: regalarlo
                            automáticamente trae problemas propios, y con un menor delante trae uno legal.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Se pide antes de</label>
                        <input type="time" name="hora_tope" class="form-control"
                               value="<?= esc($verde['hora_tope']) ?>">
                        <div class="form-text">Cuando el equipo empieza a preparar cabañas.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Máximo noches seguidas</label>
                        <input type="number" name="max_seguidas" class="form-control" min="1" max="14"
                               value="<?= (int) $verde['max_seguidas'] ?>">
                        <div class="form-text">Después toca ropa limpia. Es higiene, no gasto.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Lo que cuesta una lavada</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="coste_lavada" class="form-control" min="0" step="any"
                                   value="<?= esc($verde['coste_lavada']) ?>">
                        </div>
                        <div class="form-text">
                            Agua, energía, detergente y mano de obra. <strong>Este número no lo
                            inventamos nosotros</strong>: sin él el informe no puede decir si el
                            programa sale a cuenta, y lo dirá así.
                        </div>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Lo que lee el huésped</label>
                        <textarea name="texto" class="form-control" rows="2" maxlength="400"><?= esc($verde['texto']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="activo" id="verde_activo"
                                   <?= $verde['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="verde_activo">
                                Ofrecerlo en el portal y en recepción
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                        <a href="<?= site_url('verde') ?>" class="btn btn-link">Ver el tablero y el balance</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6" id="mantenimiento">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-wrench-adjustable me-2 text-secondary"></i>Mantenimiento
            </div>
            <form method="post" action="<?= site_url('administracion/mantenimiento') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <p class="text-muted small">
                        Los plazos no son una promesa a nadie de fuera. Sirven para ver de un vistazo
                        <strong>qué se está quedando atrás</strong>, que es lo que de verdad pasa: no
                        que nadie arregle nada, sino que algo lleve tres semanas ahí sin que nadie
                        se dé cuenta.
                    </p>

                    <label class="form-label">Plazo por prioridad</label>
                    <div class="row g-2 mb-3">
                        <?php foreach (['urgente' => 'Urgente', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'] as $p => $etiqueta): ?>
                            <div class="col-6 col-sm-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text" style="min-width: 62px;"><?= $etiqueta ?></span>
                                    <input type="number" name="sla_<?= $p ?>" class="form-control" min="1" max="720"
                                           value="<?= (int) $mantenimiento['sla'][$p] ?>">
                                    <span class="input-group-text">h</span>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Coste de una hora de técnico</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="costo_hora" class="form-control" min="0" step="any"
                                       value="<?= (float) $mantenimiento['costo_hora'] ?>">
                            </div>
                            <div class="form-text">
                                <strong>En 0 no se suma nada</strong>, que es mejor que sumar
                                una cifra inventada. Sirve para comparar reparar contra cambiar.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prefijo de los códigos</label>
                            <input type="text" name="prefijo" class="form-control text-uppercase font-monospace"
                                   maxlength="6" value="<?= esc($mantenimiento['prefijo']) ?>">
                            <div class="form-text">
                                El siguiente equipo será <span class="font-monospace"><?= esc($mantenimiento['prefijo']) ?>-0001</span>.
                                Los ya dados de alta no cambian: sus etiquetas están pegadas.
                            </div>
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="verifica" value="1" id="mantVerifica"
                               <?= $mantenimiento['verifica'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="mantVerifica">
                            <strong>Doble firma en lo urgente</strong>
                            <span class="d-block form-text">
                                Las averías urgentes y las que bloquean una cabaña necesitan que
                                <strong>otra persona</strong> las dé por buenas antes de cerrarlas.
                                Dar por arreglada una fuga de gas con la palabra de quien la arregló
                                y volver a vender la cabaña esa tarde es lo que esto impide.
                            </span>
                        </label>
                    </div>

                    <button class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Registro de llegada · <?= esc($hotel->nombre) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bosque: #1f4d36;
            --bosque-claro: #2a5f44;
            --arena: #b9873f;
            --crema: #f7f4ee;
            --tinta: #22302a;
            --suave: #6d7a72;
            --borde: #ddd7cc;
            --exito: #2f7d52;
            --error: #b3454a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--crema); color: var(--tinta);
            font-family: 'Inter', system-ui, sans-serif; font-size: 16px; line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        h1, h2 { font-family: 'Fraunces', Georgia, serif; }

        /* ── Cabecera ── */
        .cabecera {
            background: linear-gradient(150deg, var(--bosque), var(--bosque-claro));
            color: #fff; padding: 26px 20px 34px; text-align: center;
        }
        .cabecera .marca { font-size: .78rem; letter-spacing: .16em; text-transform: uppercase; opacity: .75; }
        .cabecera h1 { font-size: 1.5rem; margin: 6px 0 4px; }
        .cabecera .sub { opacity: .85; font-size: .92rem; }

        /* ── Tarjeta de la reserva ── */
        .envoltorio { max-width: 640px; margin: -22px auto 40px; padding: 0 14px; }
        .tarjeta {
            background: #fff; border: 1px solid var(--borde); border-radius: 16px;
            padding: 18px; margin-bottom: 16px; box-shadow: 0 2px 10px rgba(34,48,42,.06);
        }
        .reserva { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .reserva .dato .et { font-size: .74rem; text-transform: uppercase; letter-spacing: .08em; color: var(--suave); }
        .reserva .dato .vl { font-weight: 600; }

        /* ── Progreso ── */
        .pasos { display: flex; gap: 6px; margin-bottom: 18px; }
        .paso { flex: 1; height: 5px; border-radius: 99px; background: var(--borde); }
        .paso.hecho { background: var(--exito); }

        /* ── Secciones ── */
        .seccion-tit { display: flex; align-items: center; gap: 10px; margin: 0 0 4px; font-size: 1.12rem; }
        .seccion-tit .num {
            width: 28px; height: 28px; border-radius: 50%; background: var(--bosque); color: #fff;
            display: inline-flex; align-items: center; justify-content: center; font-size: .85rem;
            font-family: 'Inter', sans-serif; font-weight: 700; flex-shrink: 0;
        }
        .seccion-tit .num.ok { background: var(--exito); }
        .ayuda { color: var(--suave); font-size: .88rem; margin: 0 0 14px; }

        /* ── Formularios ── */
        label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: 4px; }
        label .opc { font-weight: 400; color: var(--suave); }
        input[type=text], input[type=email], input[type=tel], input[type=date],
        input[type=time], select, textarea {
            width: 100%; padding: 13px 14px; border: 1px solid var(--borde); border-radius: 11px;
            font-size: 16px; font-family: inherit; background: #fff; color: var(--tinta); margin-bottom: 13px;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: var(--bosque); box-shadow: 0 0 0 3px rgba(31,77,54,.12);
        }
        .fila { display: flex; gap: 10px; }
        .fila > div { flex: 1; min-width: 0; }
        .obligatorio { color: var(--error); }

        .boton {
            width: 100%; padding: 15px; border-radius: 12px; border: none; cursor: pointer;
            font-size: 1rem; font-weight: 600; font-family: inherit;
            background: var(--bosque); color: #fff; display: flex;
            align-items: center; justify-content: center; gap: 8px;
        }
        .boton:active { transform: scale(.99); }
        .boton.secundario { background: #fff; color: var(--bosque); border: 1px solid var(--bosque); }
        .boton.grande { padding: 18px; font-size: 1.06rem; }

        /* ── Listas ── */
        .item {
            display: flex; justify-content: space-between; align-items: center; gap: 10px;
            padding: 12px 0; border-bottom: 1px solid var(--borde);
        }
        .item:last-child { border-bottom: none; }
        .item .nom { font-weight: 600; }
        .item .det { font-size: .84rem; color: var(--suave); }
        .quitar {
            background: none; border: 1px solid var(--borde); border-radius: 9px;
            width: 40px; height: 40px; color: var(--error); cursor: pointer; flex-shrink: 0;
        }
        .etiqueta {
            display: inline-block; font-size: .74rem; font-weight: 600; border-radius: 99px;
            padding: 2px 9px; background: #f0ebe0; color: #6b5c3f;
        }
        .etiqueta.menor { background: #fdeaea; color: #9a3b40; }

        /* ── Subida de documentos ── */
        .soltar {
            border: 2px dashed var(--borde); border-radius: 14px; padding: 24px 16px;
            text-align: center; background: #fdfcfa; cursor: pointer; display: block;
        }
        .soltar i { font-size: 1.9rem; color: var(--arena); display: block; margin-bottom: 6px; }
        .soltar .t { font-weight: 600; }
        .soltar .s { font-size: .84rem; color: var(--suave); }
        input[type=file] { display: none; }
        .doc {
            display: flex; align-items: center; gap: 10px; padding: 10px;
            border: 1px solid var(--borde); border-radius: 11px; margin-bottom: 8px; background: #fff;
        }
        .doc i { font-size: 1.4rem; color: var(--bosque); }
        .doc .n { flex: 1; min-width: 0; font-size: .88rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ── Avisos legales ── */
        .legal {
            background: #fdfcfa; border: 1px solid var(--borde); border-left: 4px solid var(--arena);
            border-radius: 11px; padding: 14px; margin-bottom: 12px; font-size: .84rem; color: #4a5450;
        }
        .legal strong { color: var(--tinta); }
        .legal .titulo { font-weight: 700; margin-bottom: 5px; display: block; }
        .acepta { display: flex; gap: 11px; align-items: flex-start; margin-bottom: 14px; }
        .acepta input { width: 24px; height: 24px; flex-shrink: 0; margin-top: 1px; accent-color: var(--bosque); }
        .acepta label { font-size: .88rem; font-weight: 400; margin: 0; }

        /* ── Firma ── */
        .lienzo-caja { position: relative; }
        #lienzo {
            width: 100%; height: 190px; border: 2px dashed var(--borde);
            border-radius: 12px; background: #fff; touch-action: none; display: block;
        }
        .marca-agua {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            color: #c9c3b6; pointer-events: none; font-size: .92rem;
        }

        /* ── Mensajes ── */
        .aviso {
            padding: 13px 15px; border-radius: 11px; margin-bottom: 15px; font-size: .9rem;
            display: flex; gap: 9px; align-items: flex-start;
        }
        .aviso.ok { background: #e7f3ec; color: #1d5c3c; border: 1px solid #b8dcc7; }
        .aviso.mal { background: #fbeaea; color: #8f3237; border: 1px solid #eec4c6; }

        .estado-enviado { text-align: center; padding: 26px 16px; }
        .estado-enviado .icono { font-size: 3.2rem; color: var(--exito); }
        .pie { text-align: center; color: var(--suave); font-size: .82rem; padding: 10px 20px 30px; }
    </style>
</head>
<body>

<?php
$pasoDatos = $registro['fecha_nacimiento'] !== null && $registro['motivo_viaje'] !== null;
$pasoDocs  = ! empty($documentos);
$enviado   = in_array($registro['estado'], ['enviado', 'aprobado'], true);
$rechazado = $registro['estado'] === 'rechazado';
?>

<header class="cabecera">
    <div class="marca"><?= esc($hotel->nombre) ?></div>
    <h1>Registro de llegada</h1>
    <div class="sub">Reserva <?= esc($registro['codigo']) ?></div>
</header>

<div class="envoltorio">

    <?php if (session()->getFlashdata('ok')): ?>
        <div class="aviso ok"><i class="bi bi-check-circle-fill"></i><span><?= esc(session()->getFlashdata('ok')) ?></span></div>
    <?php endif ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="aviso mal"><i class="bi bi-exclamation-triangle-fill"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
    <?php endif ?>

    <!-- ═══ Resumen de la reserva ═══ -->
    <div class="tarjeta">
        <div class="reserva">
            <div class="dato">
                <div class="et">Llegada</div>
                <div class="vl"><?= date('d/m/Y', strtotime($registro['fecha_entrada'])) ?></div>
            </div>
            <div class="dato">
                <div class="et">Salida</div>
                <div class="vl"><?= date('d/m/Y', strtotime($registro['fecha_salida'])) ?></div>
            </div>
            <div class="dato">
                <div class="et">Alojamiento</div>
                <div class="vl"><?= esc($registro['unidad_nombre']) ?></div>
            </div>
        </div>
    </div>

    <?php if ($enviado): ?>
        <!-- ═══ Ya enviado ═══ -->
        <div class="tarjeta estado-enviado">
            <div class="icono"><i class="bi bi-<?= $registro['estado'] === 'aprobado' ? 'patch-check-fill' : 'send-check-fill' ?>"></i></div>
            <h2 style="margin:10px 0 6px"><?= $registro['estado'] === 'aprobado' ? '¡Todo listo!' : 'Registro enviado' ?></h2>
            <p class="ayuda" style="margin-bottom:0">
                <?= $registro['estado'] === 'aprobado'
                    ? 'Tu registro fue revisado y aprobado. A tu llegada solo tendremos que entregarte la llave.'
                    : 'Hemos recibido tus datos. Los revisaremos antes de tu llegada y te avisaremos si falta algo.' ?>
            </p>
        </div>
        <p class="pie">
            ¿Necesitas cambiar algo? Escríbenos por WhatsApp al <?= esc($hotel->telefono) ?>.
        </p>
    <?php else: ?>

        <?php if ($rechazado && $registro['motivo_rechazo']): ?>
            <div class="aviso mal">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span><strong>Necesitamos que corrijas algo:</strong><br><?= esc($registro['motivo_rechazo']) ?></span>
            </div>
        <?php endif ?>

        <div class="pasos">
            <div class="paso <?= $pasoDatos ? 'hecho' : '' ?>"></div>
            <div class="paso <?= $pasoDocs ? 'hecho' : '' ?>"></div>
            <div class="paso"></div>
        </div>

        <!-- ═══ 1. Datos del titular ═══ -->
        <div class="tarjeta" id="datos">
            <h2 class="seccion-tit"><span class="num <?= $pasoDatos ? 'ok' : '' ?>"><?= $pasoDatos ? '✓' : '1' ?></span>Tus datos</h2>
            <p class="ayuda">Los pide la normativa colombiana de alojamiento. Solo te llevará un minuto.</p>

            <form method="post" action="<?= site_url('registro/' . $token . '/datos') ?>">
                <?= csrf_field() ?>
                <div class="fila">
                    <div>
                        <label>Nombre <span class="obligatorio">*</span></label>
                        <input type="text" name="nombre" value="<?= esc($registro['nombre']) ?>" required>
                    </div>
                    <div>
                        <label>Apellidos <span class="obligatorio">*</span></label>
                        <input type="text" name="apellidos" value="<?= esc($registro['apellidos']) ?>" required>
                    </div>
                </div>

                <label>Documento de identidad</label>
                <input type="text" value="<?= esc($registro['tipo_documento'] . ' ' . $registro['num_documento']) ?>" disabled
                       style="background:#f4f2ed; color:var(--suave)">

                <div class="fila">
                    <div>
                        <label>Fecha de nacimiento <span class="obligatorio">*</span></label>
                        <input type="date" name="fecha_nacimiento" value="<?= esc($registro['fecha_nacimiento'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label>Nacionalidad <span class="obligatorio">*</span></label>
                        <input type="text" name="nacionalidad" value="<?= esc($registro['nacionalidad'] ?: 'Colombia') ?>" required>
                    </div>
                </div>

                <div class="fila">
                    <div>
                        <label>País de residencia</label>
                        <input type="text" name="pais_residencia" value="<?= esc($registro['pais_residencia'] ?? 'Colombia') ?>">
                    </div>
                    <div>
                        <label>Ciudad de residencia <span class="obligatorio">*</span></label>
                        <input type="text" name="ciudad_residencia" value="<?= esc($registro['ciudad_residencia'] ?? '') ?>" required>
                    </div>
                </div>

                <label>Dirección <span class="opc">(opcional)</span></label>
                <input type="text" name="direccion" value="<?= esc($registro['direccion'] ?? '') ?>">

                <div class="fila">
                    <div>
                        <label>Teléfono <span class="obligatorio">*</span></label>
                        <input type="tel" name="telefono" value="<?= esc($registro['telefono'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label>Correo electrónico</label>
                        <input type="email" name="email" value="<?= esc($registro['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="fila">
                    <div>
                        <label>Motivo del viaje <span class="obligatorio">*</span></label>
                        <select name="motivo_viaje" required>
                            <option value="">Elige…</option>
                            <?php foreach ($motivos as $clave => $etiqueta): ?>
                                <option value="<?= $clave ?>" <?= ($registro['motivo_viaje'] ?? '') === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div>
                        <label>Ocupación <span class="opc">(opcional)</span></label>
                        <input type="text" name="ocupacion" value="<?= esc($registro['ocupacion'] ?? '') ?>">
                    </div>
                </div>

                <div class="fila">
                    <div>
                        <label>Hora estimada de llegada</label>
                        <input type="time" name="hora_llegada" value="<?= esc($registro['hora_llegada'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Placa del vehículo <span class="opc">(si vienes en carro)</span></label>
                        <input type="text" name="placa_vehiculo" value="<?= esc($registro['placa_vehiculo'] ?? '') ?>" placeholder="ABC123">
                    </div>
                </div>

                <label>Alergias, dieta o algo que debamos saber <span class="opc">(opcional)</span></label>
                <textarea name="observaciones" rows="2"><?= esc($registro['observaciones'] ?? '') ?></textarea>

                <button class="boton"><i class="bi bi-check-lg"></i>Guardar mis datos</button>
            </form>
        </div>

        <!-- ═══ 2. Acompañantes ═══ -->
        <div class="tarjeta" id="acompanantes">
            <h2 class="seccion-tit"><span class="num">2</span>¿Vienes acompañado?</h2>
            <p class="ayuda">
                Debemos registrar a todas las personas que se alojan.
                Tu reserva es para <?= esc($registro['adultos']) ?> adulto<?= $registro['adultos'] > 1 ? 's' : '' ?><?= $registro['ninos'] > 0 ? ' y ' . esc($registro['ninos']) . ' niño' . ($registro['ninos'] > 1 ? 's' : '') : '' ?>.
            </p>

            <?php if (! empty($acompanantes)): ?>
                <div style="margin-bottom:14px">
                    <?php foreach ($acompanantes as $a): ?>
                        <div class="item">
                            <div>
                                <div class="nom">
                                    <?= esc($a['nombre'] . ' ' . $a['apellidos']) ?>
                                    <?php if ($a['es_menor']): ?><span class="etiqueta menor">Menor de edad</span><?php endif ?>
                                </div>
                                <div class="det">
                                    <?= esc($a['tipo_documento']) ?> <?= esc($a['num_documento'] ?: 'sin documento') ?>
                                    · <?= esc($a['nacionalidad']) ?>
                                    <?= $a['parentesco'] ? ' · ' . esc($a['parentesco']) : '' ?>
                                </div>
                            </div>
                            <form method="post" action="<?= site_url('registro/' . $token . '/acompanante/eliminar/' . $a['id']) ?>">
                                <?= csrf_field() ?>
                                <button class="quitar" title="Quitar"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <details>
                <summary style="cursor:pointer; font-weight:600; padding:10px 0; color:var(--bosque)">
                    <i class="bi bi-person-plus"></i> Añadir acompañante
                </summary>
                <form method="post" action="<?= site_url('registro/' . $token . '/acompanante') ?>" style="margin-top:12px">
                    <?= csrf_field() ?>
                    <div class="fila">
                        <div>
                            <label>Nombre <span class="obligatorio">*</span></label>
                            <input type="text" name="nombre" required>
                        </div>
                        <div>
                            <label>Apellidos <span class="obligatorio">*</span></label>
                            <input type="text" name="apellidos" required>
                        </div>
                    </div>
                    <div class="fila">
                        <div>
                            <label>Tipo de documento</label>
                            <select name="tipo_documento">
                                <?php foreach ($tiposDoc as $clave => $etiqueta): ?>
                                    <option value="<?= $clave ?>"><?= $etiqueta ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div>
                            <label>Número</label>
                            <input type="text" name="num_documento">
                        </div>
                    </div>
                    <div class="fila">
                        <div>
                            <label>Fecha de nacimiento <span class="obligatorio">*</span></label>
                            <input type="date" name="fecha_nacimiento" required>
                        </div>
                        <div>
                            <label>Nacionalidad</label>
                            <input type="text" name="nacionalidad" value="Colombia">
                        </div>
                    </div>
                    <label>Parentesco o relación <span class="opc">(opcional)</span></label>
                    <input type="text" name="parentesco" placeholder="Pareja, hijo/a, amigo/a…">
                    <button class="boton secundario"><i class="bi bi-plus-lg"></i>Añadir</button>
                </form>
            </details>
        </div>

        <!-- ═══ 3. Documentos ═══ -->
        <div class="tarjeta" id="documentos">
            <h2 class="seccion-tit"><span class="num <?= $pasoDocs ? 'ok' : '' ?>"><?= $pasoDocs ? '✓' : '3' ?></span>Tu documento</h2>
            <p class="ayuda">
                Toma una foto de tu documento de identidad. Se guarda cifrado en nuestro sistema,
                solo lo ve el personal de recepción y se elimina cuando ya no es necesario conservarlo.
            </p>

            <?php if (! empty($documentos)): ?>
                <div style="margin-bottom:14px">
                    <?php foreach ($documentos as $d): ?>
                        <div class="doc">
                            <i class="bi bi-<?= str_contains((string) $d['mime'], 'pdf') ? 'file-earmark-pdf' : 'file-earmark-image' ?>"></i>
                            <span class="n">
                                <?= esc(\App\Models\DocumentoModel::TIPOS[$d['tipo']] ?? 'Documento') ?>
                                <span style="color:var(--suave)">· <?= round($d['tamano'] / 1024) ?> KB</span>
                            </span>
                            <form method="post" action="<?= site_url('registro/' . $token . '/documento/eliminar/' . $d['id']) ?>">
                                <?= csrf_field() ?>
                                <button class="quitar"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <form method="post" action="<?= site_url('registro/' . $token . '/documento') ?>" enctype="multipart/form-data" id="formDoc">
                <?= csrf_field() ?>
                <label>¿Qué vas a subir?</label>
                <select name="tipo">
                    <?php foreach (\App\Models\DocumentoModel::TIPOS as $clave => $etiqueta): ?>
                        <option value="<?= $clave ?>"><?= $etiqueta ?></option>
                    <?php endforeach ?>
                </select>

                <label for="archivo" class="soltar">
                    <i class="bi bi-camera"></i>
                    <span class="t">Tomar foto o elegir archivo</span>
                    <span class="s">JPG, PNG o PDF · máximo 8 MB</span>
                </label>
                <input type="file" name="documento" id="archivo" accept="image/*,application/pdf" capture="environment">
            </form>

            <?php if (! empty($acompanantes) && $registro['hay_menores']): ?>
                <div class="legal" style="border-left-color:var(--error)">
                    <span class="titulo"><i class="bi bi-shield-exclamation"></i> Viajas con menores de edad</span>
                    Debes aportar el documento del menor. Si el menor no viaja con ambos padres,
                    la ley exige además la <strong>autorización de viaje</strong> firmada por quien no lo acompaña.
                    Podremos pedirte estos documentos en el momento de la llegada.
                </div>
            <?php endif ?>
        </div>

        <!-- ═══ 4. Autorizaciones y firma ═══ -->
        <div class="tarjeta" id="firma">
            <h2 class="seccion-tit"><span class="num">4</span>Autorizaciones y firma</h2>

            <form method="post" action="<?= site_url('registro/' . $token . '/enviar') ?>" id="formEnviar">
                <?= csrf_field() ?>

                <div class="legal">
                    <span class="titulo">Tratamiento de datos personales</span>
                    <?= esc($hotel->nombre) ?>, responsable del tratamiento, recogerá tus datos con la
                    finalidad de gestionar tu alojamiento, cumplir las obligaciones legales de registro
                    de huéspedes ante las autoridades de turismo y migración, y facturar los servicios.
                    Puedes conocer, actualizar, rectificar o suprimir tus datos, y revocar esta autorización,
                    escribiendo a <strong><?= esc($hotel->email) ?></strong>.
                    Conforme a la <strong>Ley 1581 de 2012</strong> y el Decreto 1377 de 2013.
                </div>
                <div class="acepta">
                    <input type="checkbox" name="acepta_datos" value="1" id="c1" required>
                    <label for="c1">Autorizo el tratamiento de mis datos personales y los de mis acompañantes en los términos descritos. <span class="obligatorio">*</span></label>
                </div>

                <div class="legal">
                    <span class="titulo">Reglamento del alojamiento</span>
                    Declaro que la información aportada es veraz. Me comprometo a respetar las normas
                    del establecimiento, los horarios de entrada y salida, el cuidado del entorno natural
                    y a responder por los daños que pudiera ocasionar durante la estancia.
                </div>
                <div class="acepta">
                    <input type="checkbox" name="acepta_reglamento" value="1" id="c2" required>
                    <label for="c2">He leído y acepto el reglamento del alojamiento. <span class="obligatorio">*</span></label>
                </div>

                <div class="legal" style="border-left-color:var(--error)">
                    <span class="titulo"><i class="bi bi-shield-check"></i> Protección de niños, niñas y adolescentes</span>
                    La explotación y el abuso sexual de menores de edad en Colombia son <strong>sancionados
                    penal y administrativamente</strong>, conforme a las leyes <strong>679 de 2001</strong> y
                    <strong>1336 de 2009</strong>. Este establecimiento rechaza cualquier forma de explotación
                    sexual comercial de niños, niñas y adolescentes y colabora activamente con las autoridades.
                </div>
                <div class="acepta">
                    <input type="checkbox" name="acepta_escnna" value="1" id="c3" required>
                    <label for="c3">He leído y comprendo esta advertencia. <span class="obligatorio">*</span></label>
                </div>

                <div class="acepta">
                    <input type="checkbox" name="acepta_marketing" value="1" id="c4">
                    <label for="c4">Quiero recibir novedades y promociones del ecolodge <span class="opc">(opcional, puedes darte de baja cuando quieras)</span></label>
                </div>

                <label style="margin-top:6px">Firma <span class="obligatorio">*</span></label>
                <p class="ayuda">Dibuja tu firma con el dedo dentro del recuadro.</p>
                <div class="lienzo-caja">
                    <canvas id="lienzo"></canvas>
                    <div class="marca-agua" id="marcaAgua">Firma aquí</div>
                </div>
                <button type="button" class="boton secundario" id="borrarFirma" style="margin:8px 0 16px">
                    <i class="bi bi-eraser"></i>Borrar firma
                </button>
                <input type="hidden" name="firma" id="firmaDato">

                <button class="boton grande"><i class="bi bi-send-fill"></i>Enviar mi registro</button>
                <p class="ayuda" style="text-align:center; margin:12px 0 0">
                    Al enviar quedará registrada la fecha, la hora y el dispositivo desde el que firmas.
                </p>
            </form>
        </div>
    <?php endif ?>

    <p class="pie">
        <?= esc($hotel->nombre) ?><br>
        <?= esc($hotel->direccion) ?> · <?= esc($hotel->telefono) ?>
    </p>
</div>

<script>
(function () {
    'use strict';

    // El archivo se envía en cuanto se elige, sin botón extra
    const archivo = document.getElementById('archivo');
    if (archivo) {
        archivo.addEventListener('change', function () {
            if (this.files.length) {
                document.querySelector('.soltar .t').textContent = 'Subiendo…';
                document.getElementById('formDoc').submit();
            }
        });
    }

    // ── Firma con el dedo ──
    const lienzo = document.getElementById('lienzo');
    if (!lienzo) return;

    const ctx = lienzo.getContext('2d');
    let dibujando = false;
    let hayTrazo = false;

    function ajustar() {
        const escala = window.devicePixelRatio || 1;
        const caja = lienzo.getBoundingClientRect();
        lienzo.width = caja.width * escala;
        lienzo.height = caja.height * escala;
        ctx.scale(escala, escala);
        ctx.lineWidth = 2.4;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#22302a';
    }
    ajustar();
    window.addEventListener('resize', () => { const d = hayTrazo; ajustar(); if (!d) limpiar(); });

    function posicion(e) {
        const caja = lienzo.getBoundingClientRect();
        const p = e.touches ? e.touches[0] : e;
        return { x: p.clientX - caja.left, y: p.clientY - caja.top };
    }

    function empezar(e) {
        e.preventDefault();
        dibujando = true;
        const p = posicion(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }

    function mover(e) {
        if (!dibujando) return;
        e.preventDefault();
        const p = posicion(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        if (!hayTrazo) {
            hayTrazo = true;
            document.getElementById('marcaAgua').style.display = 'none';
        }
    }

    function parar() { dibujando = false; }

    lienzo.addEventListener('mousedown', empezar);
    lienzo.addEventListener('mousemove', mover);
    lienzo.addEventListener('mouseup', parar);
    lienzo.addEventListener('mouseleave', parar);
    lienzo.addEventListener('touchstart', empezar, { passive: false });
    lienzo.addEventListener('touchmove', mover, { passive: false });
    lienzo.addEventListener('touchend', parar);

    function limpiar() {
        ctx.clearRect(0, 0, lienzo.width, lienzo.height);
        hayTrazo = false;
        document.getElementById('marcaAgua').style.display = 'flex';
    }
    document.getElementById('borrarFirma').addEventListener('click', limpiar);

    // Al enviar, la firma viaja como imagen
    document.getElementById('formEnviar').addEventListener('submit', function (e) {
        if (!hayTrazo) {
            e.preventDefault();
            alert('Por favor, dibuja tu firma antes de enviar.');
            document.getElementById('lienzo').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        document.getElementById('firmaDato').value = lienzo.toDataURL('image/png');
    });
}());
</script>

<?= view("partes/espera") ?>
</body>
</html>

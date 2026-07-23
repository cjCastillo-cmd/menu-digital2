/* ============================================================
   Menu del cliente.
   El precio que se muestra aqui es una vista previa: el que
   cuenta lo vuelve a calcular PHP antes de guardar el pedido.
   ============================================================ */
(function () {
  "use strict";

  var D = window.DATOS;
  var $ = function (s, c) { return (c || document).querySelector(s); };

  var PROD = {}, GRUPO = {};
  D.catalogo.productos.forEach(function (p) { PROD[p.id] = p; });
  D.catalogo.grupos.forEach(function (g) { GRUPO[g.id] = g; });

  var estado = { modo: "mesa", zona: (D.zonas[0] || {}).nombre || "", carrito: [], borrador: null, propinaPct: 0, cupon: "" };

  function esc(t) {
    return String(t == null ? "" : t).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function dinero(n) {
    return D.negocio.moneda + " " + Math.round(n).toLocaleString("es-HN");
  }

  function semilla(txt) {
    var h = 2166136261;
    for (var i = 0; i < txt.length; i++) { h ^= txt.charCodeAt(i); h = Math.imul(h, 16777619); }
    return function () { h = Math.imul(h ^ (h >>> 15), 2246822507); h ^= h >>> 13; return ((h >>> 0) % 10000) / 10000; };
  }

  function gruposDe(p) {
    return (p.grupos || []).map(function (id) { return GRUPO[id]; }).filter(Boolean);
  }

  function grupoExtras(p) {
    return gruposDe(p).filter(function (g) { return g.escala; })[0] || null;
  }

  function saboresMitad() {
    return D.catalogo.productos.filter(function (p) { return p.mitades && p.disponible; });
  }

  /* ---------- Precio ---------- */

  function factorDe(d) {
    var f = 1;
    gruposDe(d.prod).forEach(function (g) {
      (d.sel[g.id] || []).forEach(function (oid) {
        var o = g.opciones.filter(function (x) { return x.id === oid; })[0];
        if (o && Math.abs(o.factor - 1) > 0.001) { f = o.factor; }
      });
    });
    return f;
  }

  function precio(d) {
    var total;
    if (d.mitades) {
      total = Math.max(PROD[d.izq.sabor].precio, PROD[d.der.sabor].precio);
    } else {
      total = d.prod.precio;
    }

    var f = factorDe(d);
    var gEx = grupoExtras(d.prod);

    gruposDe(d.prod).forEach(function (g) {
      if (gEx && g.id === gEx.id && d.mitades) { return; }
      (d.sel[g.id] || []).forEach(function (oid) {
        var o = g.opciones.filter(function (x) { return x.id === oid; })[0];
        if (o) { total += o.precio * (g.escala ? f : 1); }
      });
    });

    if (gEx && d.mitades) {
      var pr = function (id) {
        var o = gEx.opciones.filter(function (x) { return x.id === id; })[0];
        return o ? o.precio : 0;
      };
      d.izq.extras.forEach(function (i) { total += pr(i) * 0.5 * f; });
      d.der.extras.forEach(function (i) { total += pr(i) * 0.5 * f; });
    }

    return Math.round(total);
  }

  function subtotalActual() {
    return estado.carrito.reduce(function (s, l) { return s + l.precio * l.cantidad; }, 0);
  }

  function envio() {
    if (estado.modo !== "domicilio") { return 0; }
    var n = D.negocio, base;
    if (n.envioModo === "gratis") { base = 0; }
    else if (n.envioModo === "fijo") { base = n.envioFijo || 0; }
    else {
      var z = D.zonas.filter(function (x) { return x.nombre === estado.zona; })[0];
      base = z ? z.costo : 0;
    }
    // Envio gratis a partir de cierto monto (si el dueno lo activo).
    if (n.envioGratisDesde != null && subtotalActual() >= n.envioGratisDesde) { base = 0; }
    return base;
  }

  function totales() {
    var sub = estado.carrito.reduce(function (s, l) { return s + l.precio * l.cantidad; }, 0);
    var imp = Math.round(sub * D.negocio.impuesto);
    var env = envio();
    var prop = Math.round(sub * (estado.propinaPct || 0));
    return { sub: sub, imp: imp, env: env, prop: prop, total: sub + imp + env + prop };
  }

  /* ---------- Pizza ---------- */

  function svgPizza(d) {
    var gEx = grupoExtras(d.prod);

    function color(id) {
      var o = gEx ? gEx.opciones.filter(function (x) { return x.id === id; })[0] : null;
      return o && o.color ? o.color : "#C0392B";
    }

    function puntos(extras, izquierda) {
      var out = "";
      extras.forEach(function (id, idx) {
        var r = semilla(id + "-" + idx + (izquierda ? "L" : "R"));
        for (var k = 0; k < 7; k++) {
          var t = r();
          var ang = (izquierda ? Math.PI / 2 : -Math.PI / 2) + t * Math.PI;
          var rad = 22 + r() * 46;
          out += '<circle cx="' + (95 + rad * Math.cos(ang)).toFixed(1) +
                 '" cy="' + (95 + rad * Math.sin(ang)).toFixed(1) +
                 '" r="' + (4 + r() * 2).toFixed(1) + '" fill="' + color(id) + '"/>';
        }
      });
      return out;
    }

    var izq = d.mitades ? d.izq.extras : (gEx ? (d.sel[gEx.id] || []) : []);
    var der = d.mitades ? d.der.extras : izq;

    return '<svg class="pizza" viewBox="0 0 190 190" role="img" aria-label="Vista previa de la pizza">' +
      '<circle cx="95" cy="95" r="89" fill="#C08A48"/>' +
      '<circle cx="95" cy="95" r="78" fill="#B8431F"/>' +
      '<circle cx="95" cy="95" r="74" fill="#E8D8A8"/>' +
      '<g class="pizza__mitad' + (d.mitades && d.lado === "der" ? " pizza__mitad--inactiva" : "") + '" data-lado="izq">' + puntos(izq, true) + "</g>" +
      '<g class="pizza__mitad' + (d.mitades && d.lado === "izq" ? " pizza__mitad--inactiva" : "") + '" data-lado="der">' + puntos(der, false) + "</g>" +
      (d.mitades ? '<line x1="95" y1="17" x2="95" y2="173" stroke="#7A4C22" stroke-width="2" stroke-dasharray="5 4"/>' : "") +
    "</svg>";
  }

  function bloqueConstructor(d) {
    var sabores = saboresMitad();
    var opts = function (sel) {
      return sabores.map(function (p) {
        return '<option value="' + p.id + '"' + (p.id === sel ? " selected" : "") + ">" + esc(p.nombre) + "</option>";
      }).join("");
    };

    return '<div class="constructor">' +
      '<div class="constructor__cabecera">' +
        '<p class="constructor__titulo">Mitad y mitad</p>' +
        '<button class="palanca" type="button" id="palancaMitades" aria-pressed="' + d.mitades + '">' +
          (d.mitades ? "Activado" : "Activar") + "</button>" +
      "</div>" +
      svgPizza(d) +
      (d.mitades
        ? '<div class="lados">' +
            '<button class="lado" type="button" data-lado-tab="izq" aria-pressed="' + (d.lado === "izq") + '">Izquierda' +
              "<small>" + esc(PROD[d.izq.sabor].nombre) + "</small></button>" +
            '<button class="lado" type="button" data-lado-tab="der" aria-pressed="' + (d.lado === "der") + '">Derecha' +
              "<small>" + esc(PROD[d.der.sabor].nombre) + "</small></button>" +
          "</div>" +
          '<div class="campo"><label class="campo__rotulo" for="saborLado">Sabor de la mitad ' +
            (d.lado === "izq" ? "izquierda" : "derecha") + '</label>' +
            '<select id="saborLado">' + opts(d[d.lado].sabor) + "</select></div>" +
          '<p class="nota-pie">Se cobra el precio de la mitad más cara. Los extras de cada mitad valen la mitad.</p>'
        : '<p class="nota-pie">Podés pedir dos sabores en la misma pizza.</p>') +
    "</div>";
  }

  /* ---------- Grupos ---------- */

  function regla(g, n) {
    if (g.tipo === "unico") { return g.obligatorio ? "Elegí 1" : "Opcional"; }
    if (g.minimo && n < g.minimo) { return "Elegí " + g.minimo; }
    if (g.maximo) { return n + " de " + g.maximo; }
    return "Opcional";
  }

  function bloqueGrupo(g, d) {
    var gEx = grupoExtras(d.prod);
    var esExtras = gEx && g.id === gEx.id;
    var enMitad = esExtras && d.mitades;
    var elegidas = enMitad ? d[d.lado].extras : (d.sel[g.id] || []);
    var falta = (g.obligatorio && !elegidas.length) || (g.minimo && elegidas.length < g.minimo);
    var f = factorDe(d);

    var titulo = enMitad ? g.nombre + " · mitad " + (d.lado === "izq" ? "izquierda" : "derecha") : g.nombre;

    return '<div class="grupo" data-grupo="' + g.id + '">' +
      '<div class="grupo__cabecera">' +
        '<p class="grupo__nombre">' + esc(titulo) + "</p>" +
        '<span class="grupo__regla' + (falta ? " grupo__regla--falta" : "") + '">' + esc(regla(g, elegidas.length)) + "</span>" +
      "</div>" +
      g.opciones.map(function (o) {
        var activa = elegidas.indexOf(o.id) > -1;
        var extra = o.precio * (g.escala ? f * (enMitad ? 0.5 : 1) : 1);
        return '<button class="opcion" type="button" data-opcion="' + o.id + '" aria-pressed="' + activa + '">' +
          '<span class="casilla">' + (activa ? "×" : "") + "</span>" +
          '<span class="opcion__nombre">' + esc(o.nombre) + "</span>" +
          '<span class="opcion__precio">' + (extra ? "+ " + dinero(extra) : "incluido") + "</span>" +
        "</button>";
      }).join("") +
    "</div>";
  }

  function valido(d) {
    return gruposDe(d.prod).every(function (g) {
      var n = (d.sel[g.id] || []).length;
      if (g.obligatorio && !n) { return false; }
      if (g.minimo && n < g.minimo) { return false; }
      return true;
    });
  }

  function pintarBorrador() {
    var d = estado.borrador;
    var gs = gruposDe(d.prod);
    var partes = gs.map(function (g) { return bloqueGrupo(g, d); });

    if (d.prod.mitades) {
      var gEx = grupoExtras(d.prod);
      var idx = gEx ? gs.map(function (g) { return g.id; }).indexOf(gEx.id) : gs.length;
      partes.splice(idx < 0 ? gs.length : idx, 0, bloqueConstructor(d));
    }

    $("#cuerpoPlatillo").innerHTML = partes.join("");
    $("#precioPlatillo").textContent = dinero(precio(d) * d.cantidad);
    $("#cantidad").textContent = d.cantidad;
    $("#agregar").disabled = !valido(d);
  }

  function abrirPlatillo(id) {
    var p = PROD[id];
    if (!p || !p.disponible) { return; }

    var d = {
      prod: p, sel: {}, cantidad: 1, mitades: false, lado: "izq",
      izq: { sabor: p.id, extras: [] },
      der: { sabor: p.id, extras: [] }
    };

    gruposDe(p).forEach(function (g) {
      d.sel[g.id] = (g.tipo === "unico" && g.obligatorio && g.opciones.length) ? [g.opciones[0].id] : [];
    });

    estado.borrador = d;
    $("#tituloPlatillo").textContent = p.nombre;
    $("#descPlatillo").textContent = p.desc || "";
    $("#notaPlatillo").value = "";
    pintarBorrador();
    abrir("#hojaPlatillo");
  }

  /* ---------- Hojas ---------- */

  function abrir(sel) {
    var h = $(sel);
    h.hidden = false;
    document.body.classList.add("bloqueado");
    $("#velo").classList.add("velo--visible");
    requestAnimationFrame(function () { h.classList.add("hoja--visible"); });
  }

  function cerrar() {
    ["#hojaPlatillo", "#hojaPedido"].forEach(function (s) {
      var h = $(s);
      h.classList.remove("hoja--visible");
      setTimeout(function () { h.hidden = true; }, 240);
    });
    $("#velo").classList.remove("velo--visible");
    document.body.classList.remove("bloqueado");
  }

  /* ---------- Carrito ---------- */

  function detalleDe(d) {
    var out = [];
    var gEx = grupoExtras(d.prod);

    gruposDe(d.prod).forEach(function (g) {
      if (gEx && g.id === gEx.id && d.mitades) { return; }
      var sel = d.sel[g.id] || [];
      if (!sel.length) { return; }
      out.push(g.nombre + ": " + sel.map(function (id) {
        var o = g.opciones.filter(function (x) { return x.id === id; })[0];
        return o ? o.nombre : id;
      }).join(", "));
    });

    if (d.mitades) {
      [["izq", "Mitad izquierda"], ["der", "Mitad derecha"]].forEach(function (par) {
        var ex = d[par[0]].extras.map(function (id) {
          var o = gEx.opciones.filter(function (x) { return x.id === id; })[0];
          return o ? o.nombre : id;
        });
        out.push(par[1] + ": " + PROD[d[par[0]].sabor].nombre + (ex.length ? " + " + ex.join(", ") : ""));
      });
    }
    return out;
  }

  function agregar() {
    var d = estado.borrador;
    if (!valido(d)) { return; }

    estado.carrito.push({
      uid: Date.now() + "-" + Math.random().toString(36).slice(2, 7),
      producto_id: d.prod.id,
      nombre: d.mitades ? "Pizza mitad y mitad" : d.prod.nombre,
      cantidad: d.cantidad,
      precio: precio(d),
      detalle: detalleDe(d),
      nota: $("#notaPlatillo").value.trim(),
      sel: d.sel,
      mitades: d.mitades
        ? { activo: true,
            izq: { producto_id: d.izq.sabor, extras: d.izq.extras },
            der: { producto_id: d.der.sabor, extras: d.der.extras } }
        : { activo: false }
    });

    pintarBarra();
    cerrar();
  }

  function pintarBarra() {
    var n = estado.carrito.reduce(function (s, l) { return s + l.cantidad; }, 0);
    $("#barra").classList.toggle("barra--visible", n > 0);
    $("#conteo").textContent = n;
    $("#totalBarra").textContent = dinero(totales().sub);
  }

  /* ---------- Pedido ---------- */

  var ROTULO_MODO = { mesa: "En mesa", llevar: "Para llevar", domicilio: "A domicilio" };

  function pintarPedido() {
    if (!estado.carrito.length) {
      $("#cuerpoPedido").innerHTML =
        '<div class="vacio">Todavía no agregaste nada.<br>Tocá cualquier platillo para empezar.</div>';
      return;
    }

    var t = totales();

    var lineas = estado.carrito.map(function (l) {
      return '<div class="ticket__linea">' +
        "<span>" + l.cantidad + "x</span>" +
        "<div><strong>" + esc(l.nombre) + "</strong>" +
          (l.detalle.length ? '<p class="ticket__opciones">' + esc(l.detalle.join("\n")) + "</p>" : "") +
          (l.nota ? '<p class="ticket__opciones">Nota: ' + esc(l.nota) + "</p>" : "") +
          '<button class="ticket__quitar" type="button" data-quitar="' + l.uid + '">Quitar</button>' +
        "</div>" +
        "<span>" + dinero(l.precio * l.cantidad) + "</span>" +
      "</div>";
    }).join("");

    var campos = "";
    if (estado.modo === "mesa") {
      campos = '<div class="campo"><label class="campo__rotulo" for="cMesa">Número de mesa</label>' +
        '<input id="cMesa" inputmode="numeric" maxlength="10" value="' + esc(D.mesa) + '" placeholder="7"></div>';
    } else if (estado.modo === "llevar") {
      campos = '<div class="campo"><label class="campo__rotulo" for="cHora">¿A qué hora lo recogés?</label>' +
        '<input id="cHora" type="time"></div>';
    } else {
      var n = D.negocio, dm = "";
      if (n.envioModo === "zonas") {
        dm = '<div class="campo"><label class="campo__rotulo" for="cZona">Zona de entrega</label><select id="cZona">' +
          D.zonas.map(function (z) {
            return '<option value="' + esc(z.nombre) + '"' + (z.nombre === estado.zona ? " selected" : "") + ">" +
              esc(z.nombre) + " · " + dinero(z.costo) + "</option>";
          }).join("") + "</select></div>";
      } else {
        var et = n.envioModo === "gratis" ? "Envío gratis" : "Envío " + dinero(n.envioFijo || 0);
        dm = '<div class="campo"><span class="campo__rotulo">Envío</span><strong>' + esc(et) + "</strong></div>";
      }
      campos = dm +
        '<div class="campo"><label class="campo__rotulo" for="cDir">Dirección y referencia *</label>' +
        '<textarea id="cDir" maxlength="300" placeholder="Casa portón negro, frente a la pulpería"></textarea></div>';
    }

    // Upsell: si el pedido no lleva ninguna bebida, sugerimos una.
    var tieneBebida = estado.carrito.some(function (l) { return (D.bebidaIds || []).indexOf(l.producto_id) > -1; });
    var upsell = "";
    if (!tieneBebida && (D.sugeridos || []).length) {
      upsell = '<div class="campo"><span class="campo__rotulo">¿Le agregás algo de tomar?</span>' +
        '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
        D.sugeridos.map(function (s) {
          return '<button class="mini mini--activo" type="button" data-sugerido="' + s.id + '">+ ' +
            esc(s.nombre) + " · " + dinero(s.precio) + "</button>";
        }).join("") + "</div></div>";
    }

    // Incentivos y avisos para domicilio.
    var incentivo = "";
    if (estado.modo === "domicilio") {
      var nn = D.negocio, sub = t.sub;
      if (nn.pedidoMinimo > 0 && sub < nn.pedidoMinimo) {
        incentivo += '<p class="nota-pie nota-pie--alerta">Pedido mínimo a domicilio: ' + dinero(nn.pedidoMinimo) +
          ". Te faltan " + dinero(nn.pedidoMinimo - sub) + ".</p>";
      }
      if (nn.envioGratisDesde != null && sub < nn.envioGratisDesde) {
        incentivo += '<p class="nota-pie">Agregá ' + dinero(nn.envioGratisDesde - sub) + " más y el envío es gratis.</p>";
      }
    }
    if ((estado.modo === "domicilio" || estado.modo === "llevar") && D.negocio.tiempoEstimado) {
      incentivo += '<p class="nota-pie">Tiempo estimado: ' + esc(D.negocio.tiempoEstimado) + ".</p>";
    }

    $("#cuerpoPedido").innerHTML =
      '<div class="ticket">' +
        '<div class="ticket__encabezado">' + esc(D.negocio.nombre) + " · " + ROTULO_MODO[estado.modo] + "</div>" +
        lineas +
        '<div class="ticket__totales">' +
          '<div class="ticket__total"><span>Subtotal</span><span>' + dinero(t.sub) + "</span></div>" +
          (D.negocio.impuesto ? '<div class="ticket__total"><span>ISV ' + Math.round(D.negocio.impuesto * 100) + "%</span><span>" + dinero(t.imp) + "</span></div>" : "") +
          (t.env ? '<div class="ticket__total"><span>Envío</span><span>' + dinero(t.env) + "</span></div>" : "") +
          (t.prop ? '<div class="ticket__total"><span>Propina</span><span>' + dinero(t.prop) + "</span></div>" : "") +
          '<div class="ticket__total ticket__total--fuerte"><span>Total</span><span>' + dinero(t.total) + "</span></div>" +
        "</div>" +
      "</div>" +
      '<div class="dos" style="margin-top:18px">' +
        '<div class="campo" style="margin:0"><label class="campo__rotulo" for="cNombre">Tu nombre</label>' +
          '<input id="cNombre" maxlength="120" autocomplete="name"></div>' +
        '<div class="campo" style="margin:0"><label class="campo__rotulo" for="cTel">Teléfono</label>' +
          '<input id="cTel" maxlength="30" inputmode="tel" autocomplete="tel"></div>' +
      "</div>" +
      campos +
      upsell +
      '<div class="campo"><label class="campo__rotulo" for="cPago">Forma de pago</label>' +
        '<select id="cPago">' +
          (D.negocio.formasPago || ["Efectivo"]).map(function (fp) { return "<option>" + esc(fp) + "</option>"; }).join("") +
        "</select></div>" +
      '<div class="campo"><label class="campo__rotulo" for="cCupon">¿Tenés un cupón?</label>' +
        '<input id="cCupon" maxlength="30" value="' + esc(estado.cupon) + '" placeholder="Código de descuento" style="text-transform:uppercase">' +
        '<span class="nota-pie" style="display:block">El descuento se aplica al confirmar.</span></div>' +
      '<div class="campo"><span class="campo__rotulo">Propina (opcional)</span>' +
        '<div class="modos">' +
          [0, 0.10, 0.15, 0.20].map(function (pp) {
            return '<button class="modo" type="button" data-propina="' + pp + '" aria-pressed="' + (estado.propinaPct === pp) + '">' +
              (pp ? Math.round(pp * 100) + "%" : "Sin propina") + "</button>";
          }).join("") +
        "</div></div>" +
      incentivo +
      '<p class="nota-pie nota-pie--alerta" id="avisoPedido" style="display:none"></p>' +
      (D.abierto ? "" : '<p class="nota-pie nota-pie--alerta">El local está cerrado. Podés enviar el pedido y te confirmarán al abrir.</p>') +
      '<div class="pie"><button class="accion" id="enviar" type="button">' +
        "<span>Enviar el pedido</span><span>" + dinero(t.total) + "</span></button></div>" +
      '<button class="accion accion--suave" id="vaciar" type="button">Vaciar el pedido</button>';
  }

  function enviar() {
    if (!estado.carrito.length) { return; }
    var v = function (id) { var el = $(id); return el ? el.value.trim() : ""; };
    var aviso = function (msg) {
      var a = $("#avisoPedido");
      if (a) { a.textContent = msg; a.style.display = "block"; a.scrollIntoView({ behavior: "smooth", block: "center" }); }
    };

    // A domicilio: datos del cliente obligatorios + pedido minimo.
    if (estado.modo === "domicilio") {
      if (!v("#cNombre") || !v("#cTel") || !v("#cDir")) {
        aviso("Para envío a domicilio necesitamos tu nombre, teléfono y dirección."); return;
      }
      if (D.negocio.pedidoMinimo > 0 && subtotalActual() < D.negocio.pedidoMinimo) {
        aviso("El pedido mínimo a domicilio es " + dinero(D.negocio.pedidoMinimo) + "."); return;
      }
    }

    estado.cupon = v("#cCupon").toUpperCase();
    var nota = estado.modo === "llevar" && v("#cHora") ? "Recoge a las " + v("#cHora") : "";

    var carga = {
      modo: estado.modo,
      mesa: v("#cMesa"),
      cliente: v("#cNombre"),
      telefono: v("#cTel"),
      zona: estado.modo === "domicilio" ? v("#cZona") : "",
      direccion: v("#cDir"),
      pago: v("#cPago"),
      propina_pct: estado.propinaPct,
      cupon: estado.cupon,
      nota: nota,
      lineas: estado.carrito.map(function (l) {
        return {
          producto_id: l.producto_id,
          cantidad: l.cantidad,
          nota: l.nota,
          opciones: l.sel,
          mitades: l.mitades
        };
      })
    };

    $("#cargaPedido").value = JSON.stringify(carga);
    $("#enviar").disabled = true;
    $("#formPedido").submit();
  }

  /* ---------- Eventos ---------- */

  document.addEventListener("click", function (ev) {
    var el;

    if ((el = ev.target.closest("[data-modo]"))) {
      estado.modo = el.dataset.modo;
      document.querySelectorAll("[data-modo]").forEach(function (b) {
        b.setAttribute("aria-pressed", String(b.dataset.modo === estado.modo));
      });
      if (!$("#hojaPedido").hidden) { pintarPedido(); }
      return;
    }

    if ((el = ev.target.closest("[data-cat]"))) {
      document.querySelectorAll("[data-cat]").forEach(function (b) {
        b.setAttribute("aria-current", String(b === el));
      });
      // Cada categoria es su propio panel: mostramos solo el elegido.
      document.querySelectorAll(".panel-cat").forEach(function (pan) {
        pan.classList.toggle("panel-cat--activo", pan.dataset.panel === el.dataset.cat);
      });
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }

    if ((el = ev.target.closest("[data-prod]"))) { abrirPlatillo(+el.dataset.prod); return; }
    if (ev.target.closest("[data-cerrar]") || ev.target.id === "velo") { cerrar(); return; }

    if (ev.target.closest("#palancaMitades")) {
      var d = estado.borrador;
      d.mitades = !d.mitades;
      if (d.mitades) {
        var gEx = grupoExtras(d.prod);
        var previos = gEx ? (d.sel[gEx.id] || []).slice() : [];
        d.izq.extras = previos;
        d.der.extras = [];
        d.lado = "izq";
      }
      pintarBorrador();
      return;
    }

    if ((el = ev.target.closest("[data-lado-tab]"))) {
      estado.borrador.lado = el.dataset.ladoTab;
      pintarBorrador();
      return;
    }

    if ((el = ev.target.closest(".pizza__mitad")) && estado.borrador && estado.borrador.mitades) {
      estado.borrador.lado = el.dataset.lado;
      pintarBorrador();
      return;
    }

    if ((el = ev.target.closest("[data-opcion]"))) {
      var g = GRUPO[+el.closest("[data-grupo]").dataset.grupo];
      var b = estado.borrador;
      var gE = grupoExtras(b.prod);
      var enMitad = gE && g.id === gE.id && b.mitades;
      var lista = enMitad ? b[b.lado].extras : (b.sel[g.id] = b.sel[g.id] || []);
      var id = +el.dataset.opcion;
      var i = lista.indexOf(id);

      if (g.tipo === "unico") {
        lista.length = 0;
        lista.push(id);
      } else if (i > -1) {
        lista.splice(i, 1);
      } else if (!g.maximo || lista.length < g.maximo) {
        lista.push(id);
      }
      pintarBorrador();
      return;
    }

    if (ev.target.closest("#mas")) { estado.borrador.cantidad = Math.min(50, estado.borrador.cantidad + 1); pintarBorrador(); return; }
    if (ev.target.closest("#menos")) { estado.borrador.cantidad = Math.max(1, estado.borrador.cantidad - 1); pintarBorrador(); return; }
    if (ev.target.closest("#agregar")) { agregar(); return; }
    if (ev.target.closest("#verPedido")) { pintarPedido(); abrir("#hojaPedido"); return; }

    if ((el = ev.target.closest("[data-quitar]"))) {
      estado.carrito = estado.carrito.filter(function (l) { return l.uid !== el.dataset.quitar; });
      pintarBarra();
      if (!estado.carrito.length) { cerrar(); } else { pintarPedido(); }
      return;
    }

    if ((el = ev.target.closest("[data-sugerido]"))) {
      cerrar();
      setTimeout(function () { abrirPlatillo(+el.dataset.sugerido); }, 260);
      return;
    }

    if ((el = ev.target.closest("[data-propina]"))) {
      estado.propinaPct = parseFloat(el.dataset.propina) || 0;
      pintarPedido();
      return;
    }

    if (ev.target.closest("#vaciar")) { estado.carrito = []; pintarBarra(); cerrar(); return; }
    if (ev.target.closest("#enviar")) { enviar(); return; }
  });

  document.addEventListener("change", function (ev) {
    if (ev.target.id === "saborLado") {
      estado.borrador[estado.borrador.lado].sabor = +ev.target.value;
      pintarBorrador();
    }
    if (ev.target.id === "cZona") { estado.zona = ev.target.value; pintarPedido(); }
  });

  // Recordamos el cupón mientras se escribe (para que no se pierda al redibujar).
  document.addEventListener("input", function (ev) {
    if (ev.target.id === "cCupon") { estado.cupon = ev.target.value.toUpperCase(); }
  });

  document.addEventListener("keydown", function (ev) {
    if (ev.key === "Escape") { cerrar(); }
  });

  pintarBarra();
})();

/* Carrusel de promociones: auto-avance con puntos, pausa al interactuar. */
(function () {
  var promos = document.getElementById("promos");
  if (!promos) { return; }
  var pista = document.getElementById("promosPista");
  var total = parseInt(promos.getAttribute("data-total") || "0", 10);
  if (!pista || total < 2) { return; }

  var puntos = Array.prototype.slice.call(document.querySelectorAll(".promos__punto"));
  var actual = 0;
  var timer = null;

  function anchoSlide() { return pista.clientWidth; }

  function marcar(i) {
    actual = (i + total) % total;
    puntos.forEach(function (p, k) {
      var on = k === actual;
      p.classList.toggle("es-activo", on);
      p.setAttribute("aria-selected", on ? "true" : "false");
    });
  }

  function irA(i, suave) {
    marcar(i);
    pista.scrollTo({ left: actual * anchoSlide(), behavior: suave === false ? "auto" : "smooth" });
  }

  function arrancar() {
    detener();
    timer = setInterval(function () { irA(actual + 1); }, 4500);
  }
  function detener() { if (timer) { clearInterval(timer); timer = null; } }

  // Al hacer scroll manual (swipe), sincroniza el punto activo.
  var raf = null;
  pista.addEventListener("scroll", function () {
    if (raf) { return; }
    raf = requestAnimationFrame(function () {
      raf = null;
      var i = Math.round(pista.scrollLeft / anchoSlide());
      if (i !== actual) { marcar(i); }
    });
  });

  puntos.forEach(function (p) {
    p.addEventListener("click", function () {
      irA(parseInt(p.getAttribute("data-ir"), 10));
      arrancar();
    });
  });

  // Pausa mientras el dedo/cursor está encima; retoma al soltar.
  ["mouseenter", "touchstart", "focusin"].forEach(function (ev) {
    promos.addEventListener(ev, detener, { passive: true });
  });
  ["mouseleave", "touchend", "focusout"].forEach(function (ev) {
    promos.addEventListener(ev, arrancar, { passive: true });
  });
  document.addEventListener("visibilitychange", function () {
    if (document.hidden) { detener(); } else { arrancar(); }
  });
  window.addEventListener("resize", function () { irA(actual, false); });

  marcar(0);
  arrancar();
})();

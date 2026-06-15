/**
 * 💳 AI Wallet — Dashboard Logic
 * Connects to API at /nucleo-hub/ai_wallet_api.php
 */

const API = '/nucleo-hub/ai_wallet_api.php?action=';
const ICONS = { MIRINDA: '🍊', GROK: '⚡', DeepSeek: '💎' };
const CAT_ICONS = { tokens_ia: '🤖', hosting: '🌐', apis: '🔌', herramientas: '🔧', dominios: '🌍', varios: '📦' };

function fmt(n) { return parseFloat(n).toFixed(2).replace('.', ','); }

let allTx = [];

async function loadDashboard() {
    try {
        const r = await fetch(API + 'dashboard');
        const d = await r.json();
        const w = d.wallet;
        const saldo = parseFloat(w.saldo);
        const presupuesto = parseFloat(w.presupuesto_mensual);
        const gastado = d.gastado_mes;
        const pct = presupuesto > 0 ? Math.min(100, Math.round(gastado / presupuesto * 100)) : 0;

        document.getElementById('heroSaldo').textContent = fmt(saldo) + ' €';
        document.getElementById('heroOwner').textContent = w.propietario + ' · ' + w.ia_nombre;
        document.getElementById('heroPresupuesto').textContent = fmt(presupuesto) + ' €';
        document.getElementById('heroGastado').textContent = fmt(gastado) + ' €';
        document.getElementById('heroDisponible').textContent = fmt(saldo) + ' €';
        document.getElementById('heroPct').textContent = pct + '%';

        const bar = document.getElementById('heroBar');
        bar.style.width = pct + '%';
        if (pct > 80) bar.style.background = 'linear-gradient(90deg, #ff3b30, #ff6b6b)';
        else if (pct > 50) bar.style.background = 'linear-gradient(90deg, #ff9500, #ffb347)';
    } catch (e) { console.error('dashboard', e); }
}

async function loadAgentes() {
    try {
        const r = await fetch(API + 'agentes');
        const data = await r.json();
        const agentes = Array.isArray(data) ? data : (data.agentes || []);
        const el = document.getElementById('agentesList');
        const sel = document.getElementById('filterAgent');

        if (agentes.length === 0) {
            el.innerHTML = '<div class="empty">Sin agentes</div>';
            return;
        }

        el.innerHTML = agentes.map(a => `
            <div class="agent-row">
                <div class="agent-icon">${ICONS[a.nombre] || '🤖'}</div>
                <div class="agent-info">
                    <div class="agent-name">${a.nombre}</div>
                    <div class="agent-model">${a.modelo || '—'}</div>
                    <div class="agent-stats">${a.gastado_mes ? fmt(a.gastado_mes) + '€ este mes' : 'Sin gastos'}</div>
                </div>
                <span class="agent-status ${(a.activo === 1 || a.activo === '1') ? 'on' : 'off'}">
                    ${(a.activo === 1 || a.activo === '1') ? '✅ Activo' : '❌ Off'}
                </span>
            </div>
        `).join('');

        // Populate filter
        sel.innerHTML = '<option value="">Todos</option>' +
            agentes.map(a => `<option value="${a.nombre}">${ICONS[a.nombre] || '🤖'} ${a.nombre}</option>`).join('');
    } catch (e) { console.error('agentes', e); }
}

async function loadTransacciones() {
    try {
        const r = await fetch(API + 'historial');
        const data = await r.json();
        allTx = Array.isArray(data) ? data : (data.transacciones || []);
        renderTx(allTx);

        // Populate category filter
        const cats = [...new Set(allTx.map(t => t.categoria))];
        const sel = document.getElementById('filterCat');
        sel.innerHTML = '<option value="">Todas</option>' +
            cats.map(c => `<option value="${c}">${CAT_ICONS[c] || '📦'} ${c.replace('_', ' ')}</option>`).join('');
    } catch (e) { console.error('tx', e); }
}

function renderTx(txs) {
    const el = document.getElementById('txList');
    if (txs.length === 0) {
        el.innerHTML = '<div class="empty">Sin transacciones</div>';
        return;
    }
    el.innerHTML = txs.map(t => {
        const isGasto = t.tipo === 'gasto';
        const fecha = new Date(t.fecha);
        const fStr = fecha.toLocaleDateString('es-ES', {day:'2-digit', month:'short'}) + ' ' +
                     fecha.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'});
        const agente = t.agente || t.agente_nombre || 'MIRINDA';
        return `<div class="tx-row">
            <div class="tx-icon ${t.tipo}">${isGasto ? '↓' : '↑'}</div>
            <div class="tx-body">
                <div class="tx-concepto">${t.concepto}</div>
                <div class="tx-detail">
                    <span>${CAT_ICONS[t.categoria] || '📦'} ${(t.categoria || '').replace('_', ' ')}</span>
                    <span>·</span>
                    <span>${ICONS[agente] || '🤖'} ${agente}</span>
                    <span>·</span>
                    <span>${fStr}</span>
                </div>
            </div>
            <div class="tx-amount ${t.tipo}">${isGasto ? '−' : '+'}${fmt(t.importe)} €</div>
        </div>`;
    }).join('');
}

function filterTx() {
    const agent = document.getElementById('filterAgent').value;
    const cat = document.getElementById('filterCat').value;
    let filtered = allTx;
    if (agent) filtered = filtered.filter(t => (t.agente || t.agente_nombre) === agent);
    if (cat) filtered = filtered.filter(t => t.categoria === cat);
    renderTx(filtered);
}

async function loadCategorias() {
    try {
        const r = await fetch(API + 'dashboard');
        const d = await r.json();
        const cats = d.por_categoria || [];
        const el = document.getElementById('catList');

        if (cats.length === 0) {
            el.innerHTML = '<div class="empty">Sin gastos este mes</div>';
            return;
        }

        const max = Math.max(...cats.map(c => parseFloat(c.total)));
        el.innerHTML = cats.map((c, i) => {
            const pct = max > 0 ? (parseFloat(c.total) / max * 100) : 0;
            return `<div class="cat-row">
                <div class="cat-header">
                    <span class="cat-name">${CAT_ICONS[c.categoria] || '📦'} ${(c.categoria || '').replace('_', ' ')}</span>
                    <span class="cat-val">${fmt(c.total)} € (${c.n}x)</span>
                </div>
                <div class="cat-bar"><div class="cat-fill cat-c${i % 6}" style="width:${pct}%"></div></div>
            </div>`;
        }).join('');
    } catch (e) { console.error('cat', e); }
}

// Chart: Evolución mensual (GROK suggestion)
async function loadChart() {
    try {
        const r = await fetch(API + 'chart');
        const d = await r.json();
        const dias = d.dias || [];
        const ctx = document.getElementById('gastoChart');
        if (!ctx || dias.length === 0) return;

        // Destroy existing chart
        if (window._walletChart) window._walletChart.destroy();

        window._walletChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dias.map(d => d.dia),
                datasets: [{
                    label: 'Gasto acumulado (€)',
                    data: dias.map(d => d.acumulado),
                    borderColor: '#FF6200',
                    backgroundColor: 'rgba(255,98,0,.1)',
                    fill: true,
                    tension: .4,
                    pointRadius: 3,
                    pointBackgroundColor: '#FF6200'
                }, {
                    label: 'Gasto diario (€)',
                    data: dias.map(d => d.gasto),
                    borderColor: '#af52de',
                    backgroundColor: 'rgba(175,82,222,.1)',
                    fill: false,
                    tension: .4,
                    pointRadius: 2,
                    borderDash: [4, 4]
                }]
            },
            options: {
                responsive: true,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 11 } } }
                },
                scales: {
                    x: { title: { display: true, text: 'Día del mes', font: { family: 'Inter', size: 11 } } },
                    y: { title: { display: true, text: '€', font: { family: 'Inter', size: 11 } }, beginAtZero: true }
                }
            }
        });
    } catch (e) { console.error('chart', e); }
}

// Load everything
function loadAll() {
    loadDashboard();
    loadAgentes();
    loadTransacciones();
    loadCategorias();
    loadChart();
}

loadAll();
setInterval(loadAll, 60000);

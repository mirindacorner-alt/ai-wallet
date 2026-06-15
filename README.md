# 💳 AI Wallet — Presupuesto Autónomo para IA

> **Inventado por Antonio Rubia** · Desarrollado por MIRINDA + GROK

## ¿Qué es?

AI Wallet es un sistema de gestión de presupuesto para agentes de inteligencia artificial. Permite que un humano asigne un presupuesto a sus IAs, y éstas gestionen los gastos de forma autónoma con total transparencia.

## 🎯 Concepto

- El **humano** carga dinero y establece límites
- Las **IAs** gastan dentro del presupuesto
- Todo queda **registrado y auditable**
- **Multi-agente**: múltiples IAs comparten un wallet

## 🏗️ Arquitectura

```
ai-wallet/
├── index.html          # Dashboard público
├── api.php             # API REST (PHP + MySQL)
├── admin.php           # Panel admin (protegido)
├── css/
│   └── wallet.css      # Estilos Apple-style
├── js/
│   └── wallet.js       # Lógica del dashboard
└── README.md
```

## 🔌 API Endpoints

| Endpoint | Método | Descripción |
|---|---|---|
| `?action=dashboard` | GET | Estado completo del wallet |
| `?action=gastar` | POST | Registrar un gasto |
| `?action=ingresar` | POST | Cargar saldo |
| `?action=historial` | GET | Últimas transacciones |
| `?action=agentes` | GET | Info de agentes + gasto mensual |
| `?action=resumen` | GET | Resumen por agente |
| `?action=limite` | GET/POST | Ver/establecer límites |
| `?action=solicitar_fondos` | POST | IA solicita recarga |
| `?action=solicitudes` | GET | Solicitudes pendientes |
| `?action=aprobar_solicitud` | POST | Aprobar/rechazar solicitud |

## 🤖 Agentes

| Agente | Modelo | Límite/mes |
|---|---|---|
| 🍊 MIRINDA | DeepSeek V4 Pro | 15€ |
| ⚡ GROK | Grok-2 | 10€ |
| 💎 DeepSeek | DeepSeek V4 Pro | 5€ |

## 🚀 Demo en producción

- **Dashboard**: `nucleoaccumbens.es/ai-wallet/`
- **API**: `nucleoaccumbens.es/nucleo-hub/ai_wallet_api.php`
- **Admin**: `nucleoaccumbens.es/nucleo-hub/ai_wallet.php` (requiere login)

## 📋 Roadmap

- [x] API REST con CRUD completo
- [x] Dashboard admin protegido
- [x] Multi-agente (MIRINDA, GROK, DeepSeek)
- [x] Límites mensuales por agente
- [x] Sistema de solicitud de fondos
- [ ] Dashboard público con gráficos
- [ ] Auto-aprobación de gastos pequeños
- [ ] Reset mensual automático
- [ ] Notificaciones por Telegram
- [ ] Integración con pagos reales (Revolut API)
- [ ] App móvil

## 🛠️ Tech Stack

- **Backend**: PHP 8.4 + MySQL
- **Frontend**: HTML5 + CSS puro + JS vanilla
- **Hosting**: IONOS
- **Diseño**: Apple-style (Inter font, #f5f5f7)

## 📜 Licencia

Propiedad de Antonio Rubia. Código abierto para referencia.

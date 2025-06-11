# YeKill Newsletter System

Ein mehrmandantenfähiges Newsletter-System mit erweiterten Funktionen für professionelles E-Mail-Marketing.

## Features

### ✅ Implementiert (Grundarchitektur)
- **Multi-Tenant Architektur**: Vollständige Mandantentrennung mit Subdomain/Domain-Support
- **Custom Framework**: Leichtgewichtiges PHP 8.4 Framework ohne Composer-Abhängigkeit
- **Sicherheit**: Session-Management, CSRF-Schutz, sichere Authentifizierung
- **Routing System**: Flexibles HTTP-Routing mit Middleware-Support
- **Database Layer**: PDO-basierte Datenbankabstraktion mit Query Builder
- **Configuration Management**: Umgebungsbasierte Konfiguration

### 🚧 Geplant (Nächste Schritte)
- **Empfängerlistenverwaltung**: Unbegrenzte Listen pro Mandant mit DSGVO-Konformität
- **Segmentierung & Tagging**: Dynamische Kontaktsegmente mit beliebigen Tags
- **Drag & Drop Editor**: Visueller Newsletter-Editor mit responsiven Vorlagen
- **Template Management**: Versionierte Vorlagenverwaltung mit Personalisierung
- **Automation Engine**: Visuelle Workflow-Erstellung mit Trigger-basierter Ausführung
- **Multi-SMTP Support**: Amazon SES, Mailgun, SendGrid, Postmark und weitere
- **Analytics & Tracking**: Umfassendes Reporting mit Öffnungs- und Klickraten
- **A/B Testing**: Kampagnen-Optimierung durch Split-Tests
- **API-First**: RESTful API für alle Funktionen
- **Omnichannel**: SMS und WhatsApp-Kampagnen (optional)

## Technische Spezifikationen

- **PHP**: 8.4+
- **Webserver**: Apache
- **Datenbank**: MySQL 8.0+
- **Frontend**: Modernes, responsives Design
- **Architektur**: MVC-Pattern mit Service Layer
- **Sicherheit**: OWASP-konforme Implementierung

## Installation

1. Repository klonen
2. Webserver auf das Projektverzeichnis konfigurieren
3. Datenbank erstellen und Konfiguration anpassen
4. Erste Migration ausführen

## Konfiguration

### Datenbank
```php
// config/database.php
return [
    'host' => 'localhost',
    'name' => 'yekill_newsletter',
    'username' => 'root',
    'password' => '',
];
```

### E-Mail Provider
```php
// config/mail.php - Unterstützt mehrere Provider
'providers' => [
    'smtp' => [...],
    'ses' => [...],
    'mailgun' => [...],
    'sendgrid' => [...],
]
```

## Multi-Tenant Setup

Das System unterstützt verschiedene Mandanten-Modi:

1. **Subdomain**: `kunde1.newsletter.com`
2. **Custom Domain**: `newsletter.kunde1.com`
3. **Path-based**: `newsletter.com/kunde1` (optional)

## Architektur-Highlights

### Framework-Kern
- **Autoloader**: PSR-4 kompatibel ohne Composer
- **Router**: Flexible Route-Definition mit Parameter-Extraktion
- **Request/Response**: HTTP-Abstraktion mit JSON-Support
- **Session**: Sichere Session-Verwaltung mit CSRF-Schutz
- **Database**: Query Builder mit Prepared Statements

### Multi-Tenant Features
- Automatische Mandanten-Erkennung
- Daten-Isolation auf Datenbankebene
- Mandanten-spezifische Konfiguration
- Benutzer-Rollen pro Mandant

### Sicherheit
- CSRF-Token-Validierung
- Session-Regeneration
- SQL-Injection-Schutz
- XSS-Prevention
- Rate Limiting (geplant)

## Entwicklungsstand

**Phase 1: Grundarchitektur** ✅
- Framework-Kern implementiert
- Multi-Tenant-Basis geschaffen
- Routing und Middleware-System
- Datenbank-Abstraktion

**Phase 2: Kernfunktionen** 🚧
- Kontakt- und Listenverwaltung
- Campaign Builder
- Template-System
- E-Mail-Versand

**Phase 3: Erweiterte Features** 📋
- Automation-Engine
- Analytics-Dashboard
- A/B-Testing
- API-Dokumentation

## Lizenz

Proprietäre Software - Alle Rechte vorbehalten

## Autor

HJN - github@nolte-imp.de

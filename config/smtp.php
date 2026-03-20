<?php
/**
 * Configuration SMTP multi-fournisseur avancée
 * COSMOS Group - Chaque canal peut utiliser différents fournisseurs avec basculement automatique
 */

$defaultFrom = [
    'from_email' => getenv('SMTP_FROM_EMAIL') ?: 'contact1@emergencemag.net',
    'from_name'  => getenv('SMTP_FROM_NAME') ?: 'COSMOS Group',
];

// Configuration des fournisseurs disponibles
$availableProviders = [
    'gmail' => [
        'name' => 'Gmail',
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',
        'auth_required' => true,
        'priority' => 1
    ],
    'outlook' => [
        'name' => 'Outlook/Hotmail',
        'host' => 'smtp-mail.outlook.com',
        'port' => 587,
        'encryption' => 'tls',
        'auth_required' => true,
        'priority' => 2
    ],
    'yahoo' => [
        'name' => 'Yahoo Mail',
        'host' => 'smtp.mail.yahoo.com',
        'port' => 587,
        'encryption' => 'tls',
        'auth_required' => true,
        'priority' => 3
    ],
    'custom' => [
        'name' => 'Serveur personnalisé',
        'host' => getenv('SMTP_HOST') ?: 'mail.emergencemag.net',
        'port' => (int)(getenv('SMTP_PORT') ?: 587),
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'auth_required' => true,
        'priority' => 4
    ],
    'sendgrid' => [
        'name' => 'SendGrid',
        'host' => 'smtp.sendgrid.net',
        'port' => 587,
        'encryption' => 'tls',
        'auth_required' => true,
        'priority' => 5
    ],
    'mailgun' => [
        'name' => 'Mailgun',
        'host' => 'smtp.mailgun.org',
        'port' => 587,
        'encryption' => 'tls',
        'auth_required' => true,
        'priority' => 6
    ]
];

// Fournisseurs par canal avec basculement automatique
$providers = [
    // Candidatures (offres.html → Envoyer ma candidature) → email + BDD + tableau de bord
    'candidature' => [
        'to_email'   => getenv('ADMIN_EMAIL') ?: 'web@emergencemag.net',
        'from_email' => getenv('SMTP_CANDIDATURE_FROM') ?: ($defaultFrom['from_email']),
        'from_name'  => getenv('SMTP_CANDIDATURE_FROM_NAME') ?: ($defaultFrom['from_name']),
        
        // Configuration multi-fournisseur avec priorités
        'providers' => [
            [
                'type' => 'custom',
                'username' => getenv('SMTP_CANDIDATURE_USERNAME') ?: (getenv('SMTP_USERNAME') ?: 'web@emergencemag.net'),
                'password' => getenv('SMTP_CANDIDATURE_PASSWORD') ?: (getenv('SMTP_PASSWORD') ?: 'd(4(K7hnwYU94V'),
                'enabled' => true,
                'priority' => 1
            ],
            [
                'type' => 'gmail',
                'username' => getenv('GMAIL_CANDIDATURE_USERNAME') ?: getenv('GMAIL_USERNAME'),
                'password' => getenv('GMAIL_CANDIDATURE_PASSWORD') ?: getenv('GMAIL_PASSWORD'),
                'enabled' => !empty(getenv('GMAIL_CANDIDATURE_USERNAME')) || !empty(getenv('GMAIL_USERNAME')),
                'priority' => 2
            ],
            [
                'type' => 'outlook',
                'username' => getenv('OUTLOOK_CANDIDATURE_USERNAME') ?: getenv('OUTLOOK_USERNAME'),
                'password' => getenv('OUTLOOK_CANDIDATURE_PASSWORD') ?: getenv('OUTLOOK_PASSWORD'),
                'enabled' => !empty(getenv('OUTLOOK_CANDIDATURE_USERNAME')) || !empty(getenv('OUTLOOK_USERNAME')),
                'priority' => 3
            ]
        ]
    ],
    
    // Newsletter (index.html + blog.html → S'abonner aux offres) → email + BDD
    'newsletter' => [
        'to_email'   => getenv('NEWS_EMAIL') ?: 'news@emergencemag.net',
        'from_email' => getenv('SMTP_NEWSLETTER_FROM') ?: ($defaultFrom['from_email']),
        'from_name'  => getenv('SMTP_NEWSLETTER_FROM_NAME') ?: ($defaultFrom['from_name']),
        
        // Configuration multi-fournisseur avec priorités
        'providers' => [
            [
                'type' => 'custom',
                'username' => getenv('SMTP_NEWSLETTER_USERNAME') ?: (getenv('SMTP_USERNAME') ?: 'news@emergencemag.net'),
                'password' => getenv('SMTP_NEWSLETTER_PASSWORD') ?: (getenv('SMTP_PASSWORD') ?: 'd(4(K7hnwYU94V'),
                'enabled' => true,
                'priority' => 1
            ],
            [
                'type' => 'sendgrid',
                'username' => getenv('SENDGRID_NEWSLETTER_USERNAME') ?: getenv('SENDGRID_USERNAME'),
                'password' => getenv('SENDGRID_NEWSLETTER_PASSWORD') ?: getenv('SENDGRID_PASSWORD'),
                'enabled' => !empty(getenv('SENDGRID_NEWSLETTER_USERNAME')) || !empty(getenv('SENDGRID_USERNAME')),
                'priority' => 2
            ],
            [
                'type' => 'mailgun',
                'username' => getenv('MAILGUN_NEWSLETTER_USERNAME') ?: getenv('MAILGUN_USERNAME'),
                'password' => getenv('MAILGUN_NEWSLETTER_PASSWORD') ?: getenv('MAILGUN_PASSWORD'),
                'enabled' => !empty(getenv('MAILGUN_NEWSLETTER_USERNAME')) || !empty(getenv('MAILGUN_USERNAME')),
                'priority' => 3
            ]
        ]
    ],
    
    // Contact (contact.html) → email + BDD
    'contact' => [
        'to_email'   => getenv('CONTACT_EMAIL') ?: 'contact1@emergencemag.net',
        'from_email' => getenv('SMTP_CONTACT_FROM') ?: ($defaultFrom['from_email']),
        'from_name'  => getenv('SMTP_CONTACT_FROM_NAME') ?: ($defaultFrom['from_name']),
        
        // Configuration multi-fournisseur avec priorités
        'providers' => [
            [
                'type' => 'custom',
                'username' => getenv('SMTP_CONTACT_USERNAME') ?: (getenv('SMTP_USERNAME') ?: 'contact1@emergencemag.net'),
                'password' => getenv('SMTP_CONTACT_PASSWORD') ?: (getenv('SMTP_PASSWORD') ?: 'd(4(K7hnwYU94V'),
                'enabled' => true,
                'priority' => 1
            ],
            [
                'type' => 'yahoo',
                'username' => getenv('YAHOO_CONTACT_USERNAME') ?: getenv('YAHOO_USERNAME'),
                'password' => getenv('YAHOO_CONTACT_PASSWORD') ?: getenv('YAHOO_PASSWORD'),
                'enabled' => !empty(getenv('YAHOO_CONTACT_USERNAME')) || !empty(getenv('YAHOO_USERNAME')),
                'priority' => 2
            ]
        ]
    ],
];

return [
    // Config globale (fallback pour confirmations utilisateur)
    'from_email' => $defaultFrom['from_email'],
    'from_name'  => $defaultFrom['from_name'],
    'host'       => getenv('SMTP_HOST') ?: 'mail.emergencemag.net',
    'port'       => (int)(getenv('SMTP_PORT') ?: 587),
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
    'username'   => getenv('SMTP_USERNAME') ?: 'contact1@emergencemag.net',
    'password'   => getenv('SMTP_PASSWORD') ?: 'd(4(K7hnwYU94V',
    
    // Rétrocompatibilité (lecture directe)
    'admin_email'   => $providers['candidature']['to_email'],
    'contact_email' => $providers['contact']['to_email'],
    'news_email'    => $providers['newsletter']['to_email'],
    
    // Multi-fournisseur : chaque canal utilise son fournisseur (to_email, from_email, from_name, host, etc.)
    'providers' => $providers,
    
    // Fournisseurs disponibles avec leurs configurations
    'available_providers' => $availableProviders,
    
    // Options avancées
    'options' => [
        'enable_fallback' => true,          // Basculement automatique en cas d'échec
        'max_retry_attempts' => 3,          // Nombre de tentatives par fournisseur
        'retry_delay' => 1,                 // Délai entre les tentatives (secondes)
        'timeout' => 30,                    // Timeout de connexion SMTP
        'enable_logging' => true,           // Activer les logs détaillés
        'simulate_sending' => false,        // Désactivé pour permettre l'envoi réel
    ]
];

/*
 * MULTI-FOURNISSEUR SMTP AVANCÉ
 * 
 * Ce système permet d'utiliser plusieurs fournisseurs SMTP avec basculement automatique :
 * - Chaque canal (candidature, newsletter, contact) peut avoir plusieurs fournisseurs
 * - En cas d'échec d'un fournisseur, le système bascule automatiquement vers le suivant
 * - Les fournisseurs sont triés par priorité (1 = priorité la plus haute)
 * 
 * FOURNISSEURS SUPPORTÉS :
 * - Gmail (smtp.gmail.com)
 * - Outlook/Hotmail (smtp-mail.outlook.com)
 * - Yahoo Mail (smtp.mail.yahoo.com)
 * - SendGrid (smtp.sendgrid.net)
 * - Mailgun (smtp.mailgun.org)
 * - Serveur personnalisé
 * 
 * CONFIGURATION DANS .ENV :
 * 
 * # Serveur personnalisé (priorité 1)
 * SMTP_HOST=mail.emergencemag.net
 * SMTP_USERNAME=contact1@emergencemag.net
 * SMTP_PASSWORD=d(4(K7hnwYU94V
 * 
 * # Gmail (priorité 2)
 * GMAIL_USERNAME=votre@gmail.com
 * GMAIL_PASSWORD=mot_de_passe_application
 * 
 * # Outlook (priorité 3)
 * OUTLOOK_USERNAME=votre@outlook.com
 * OUTLOOK_PASSWORD=votre_mot_de_passe
 * 
 * # SendGrid (pour newsletter)
 * SENDGRID_USERNAME=apikey
 * SENDGRID_PASSWORD=votre_api_key
 * 
 * # Mailgun (pour newsletter)
 * MAILGUN_USERNAME=postmaster@votre-domaine.mailgun.org
 * MAILGUN_PASSWORD=votre_api_key
 * 
 * # Yahoo (pour contact)
 * YAHOO_USERNAME=votre@yahoo.com
 * YAHOO_PASSWORD=mot_de_passe_application
 * 
 * CONFIGURATION PAR CANAL :
 * Vous pouvez spécifier des credentials différents pour chaque canal :
 * 
 * # Newsletter spécifique
 * SMTP_NEWSLETTER_USERNAME=newsletter@cosmosdrc.com
 * SENDGRID_NEWSLETTER_USERNAME=apikey
 * SENDGRID_NEWSLETTER_PASSWORD=newsletter_api_key
 * 
 * # Candidature spécifique
 * SMTP_CANDIDATURE_USERNAME=candidatures@cosmosdrc.com
 * GMAIL_CANDIDATURE_USERNAME=candidatures@gmail.com
 * 
 * # Contact spécifique
 * SMTP_CONTACT_USERNAME=contact1@emergencemag.net
 * YAHOO_CONTACT_USERNAME=contact@yahoo.com
 * 
 * FONCTIONNALITÉS :
 * - Basculement automatique en cas d'échec
 * - Retry automatique avec délai configurable
 * - Logs détaillés pour le debugging
 * - Mode simulation pour le développement
 * - Support des pièces jointes
 * - Validation des configurations
 */
?>

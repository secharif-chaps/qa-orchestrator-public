#!/bin/bash

# Script pour voir les logs facilement
# Usage: ./logs.sh [service] [options]

SERVER_IP="10.0.1.2"
USER="nmercier"

if [ $# -eq 0 ]; then
    echo "📋 Affichage de tous les logs..."
    ssh $USER@$SERVER_IP "cd /home/nmercier/mint && docker-compose -f docker-compose.prod.yml logs"
else
    case $1 in
        "frontend"|"backend"|"keycloak"|"db")
            if [ "$2" = "-f" ] || [ "$2" = "--follow" ]; then
                echo "📋 Logs en temps réel pour $1..."
                ssh $USER@$SERVER_IP "cd /home/nmercier/mint && docker-compose -f docker-compose.prod.yml logs -f $1"
            else
                echo "📋 Logs pour $1..."
                ssh $USER@$SERVER_IP "cd /home/nmercier/mint && docker-compose -f docker-compose.prod.yml logs $1"
            fi
            ;;
        "status")
            echo "📊 Status des services..."
            ssh $USER@$SERVER_IP "cd /home/nmercier/mint && docker-compose -f docker-compose.prod.yml ps"
            ;;
        "-f"|"--follow")
            echo "📋 Tous les logs en temps réel..."
            ssh $USER@$SERVER_IP "cd /home/nmercier/mint && docker-compose -f docker-compose.prod.yml logs -f"
            ;;
        *)
            echo "❌ Service inconnu: $1"
            echo ""
            echo "Usage: $0 [service] [options]"
            echo "Services disponibles:"
            echo "  frontend   - Logs du frontend Nuxt.js"
            echo "  backend    - Logs du backend FastAPI"
            echo "  keycloak   - Logs de Keycloak"
            echo "  db         - Logs de PostgreSQL"
            echo "  status     - Status de tous les services"
            echo ""
            echo "Options:"
            echo "  -f, --follow  - Logs en temps réel"
            echo ""
            echo "Exemples:"
            echo "  $0                    # Tous les logs"
            echo "  $0 backend            # Logs du backend"
            echo "  $0 backend -f         # Logs backend en temps réel"
            echo "  $0 status             # Status des services"
            exit 1
            ;;
    esac
fi
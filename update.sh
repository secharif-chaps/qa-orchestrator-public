#!/bin/bash

# Script de mise à jour pour le déploiement production
# Usage: ./update.sh [frontend|backend|all]

SERVER_IP="10.0.1.2"
USER="nmercier"
REMOTE_DIR="/home/nmercier/mint"

if [ $# -eq 0 ]; then
    echo "Usage: $0 [frontend|backend|all]"
    echo "  frontend  - Met à jour seulement le frontend"
    echo "  backend   - Met à jour seulement le backend"
    echo "  all       - Met à jour tout (équivalent à deploy-single-server.sh)"
    exit 1
fi

case $1 in
    "frontend")
        echo "🚀 Mise à jour du frontend..."
        
        # Copier les fichiers frontend
        echo "📦 Copie des fichiers frontend..."
        tar --exclude=node_modules --exclude=.git --exclude=__pycache__ --exclude=.next --exclude=dist -czf /tmp/mint-frontend.tar.gz mint-front/
        scp /tmp/mint-frontend.tar.gz $USER@$SERVER_IP:$REMOTE_DIR/
        rm /tmp/mint-frontend.tar.gz

        # Extraire et rebuild
        ssh $USER@$SERVER_IP << 'REMOTE_SCRIPT'
cd /home/nmercier/mint
tar -xzf mint-frontend.tar.gz
rm mint-frontend.tar.gz
echo "🔧 Rebuild et redémarrage du frontend..."
docker-compose -f docker-compose.prod.yml build frontend
docker-compose -f docker-compose.prod.yml up -d frontend
echo "✅ Frontend mis à jour!"
REMOTE_SCRIPT
        ;;

    "backend")
        echo "🚀 Mise à jour du backend..."
        
        # Copier les fichiers backend
        echo "📦 Copie des fichiers backend..."
        tar --exclude=node_modules --exclude=.git --exclude=__pycache__ --exclude=.next --exclude=dist -czf /tmp/mint-backend.tar.gz mint-back/
        scp /tmp/mint-backend.tar.gz $USER@$SERVER_IP:$REMOTE_DIR/
        rm /tmp/mint-backend.tar.gz

        # Extraire et rebuild
        ssh $USER@$SERVER_IP << 'REMOTE_SCRIPT'
cd /home/nmercier/mint
tar -xzf mint-backend.tar.gz
rm mint-backend.tar.gz
echo "🔧 Rebuild et redémarrage du backend..."
docker-compose -f docker-compose.prod.yml build backend
docker-compose -f docker-compose.prod.yml up -d backend
echo "✅ Backend mis à jour!"
REMOTE_SCRIPT
        ;;

    "all")
        echo "🚀 Mise à jour complète..."
        ./deploy-single-server.sh
        ;;

    *)
        echo "❌ Option invalide: $1"
        echo "Usage: $0 [frontend|backend|all]"
        exit 1
        ;;
esac

echo "🎉 Mise à jour terminée!"
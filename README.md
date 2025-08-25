Sortir.com

Projet réalisé dans le cadre de la formation Développeur Web & Web Mobile (ENI). Ce projet a pour objectif de mettre en place une application web permettant d’organiser et de gérer des sorties entre utilisateurs.

🚀 Fonctionnalités prévues:

•	- Création de compte et authentification des utilisateurs
•	- Gestion du profil utilisateur
•	- Création, modification et suppression de sorties
•	- Inscription / désinscription aux sorties
•	- Gestion des lieux et villes
•	- Rôles (utilisateur, organisateur, administrateur)
•	- Interface responsive (mobile et desktop)
🛠️ Stack technique
•	- Backend : Symfony 6 / PHP 8
•	- Base de données : MySQL / MariaDB
•	- ORM : Doctrine
•	- Frontend : Twig, HTML5, CSS3, Bootstrap
•	- Outils : Composer, Git, PhpStorm

⚙️ Installation

1.	1. Cloner le projet
git clone https://github.com/Turbo-Teflon/Sortir.git
cd Sortir.com
2.	2. Installer les dépendances
composer install
3.	3. Configurer l’environnement
Copier le fichier `.env` en `.env.local` et modifier les paramètres de connexion à la base de données :
DATABASE_URL="mysql://username:password@127.0.0.1:3306/sortir_db?serverVersion=8&charset=utf8mb4"
4.	4. Créer la base de données
symfony console doctrine:database:create
symfony console doctrine:migrations:migrate
5.	5. Lancer le serveur Symfony
symfony serve:start

👥 Équipe projet

•	- Lead : Turbo-Teflon (Marwan) (https://github.com/Turbo-Teflon)  
•	- Contributeur : Yoalgrin (Gabriel) (https://github.com/Yoalgrin)  
•	- Contributeur : aurel12321 (Aurélien) (https://github.com/aurel12321)  

📄 Licence

Projet pédagogique réalisé dans le cadre de la formation ENI. Usage libre pour l’apprentissage et l’entraînement.

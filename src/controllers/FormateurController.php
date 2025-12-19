<?php
/**
 * Contrôleur pour la gestion de l'espace Formateur
 * Gère les user stories SCRUM-14, SCRUM-15, SCRUM-16, SCRUM-17
 */
class FormateurController {
    
    private $db;
    
    public function __construct() {
        // TODO: Initialiser la connexion à la base de données
        // $this->db = Database::getInstance();
    }
    
    /**
     * Affiche le tableau de bord du formateur
     * Route: GET /formateur/dashboard
     */
    public function dashboard() {
        // Vérifie que l'utilisateur est bien un formateur
        $this->checkRole('formateur');
        
        // TODO: Récupérer les statistiques depuis la base de données
        $stats = [
            'total_etudiants' => $this->countEtudiants(),
            'total_promotions' => $this->countPromotions(),
            'total_espaces' => $this->countEspacesPedagogiques(),
            'nouveaux_etudiants' => $this->countNouveauxEtudiants()
        ];
        
        require_once __DIR__ . '/../views/formateur/dashboard.php';
    }
    
    /**
     * SCRUM-14: Affiche le formulaire de création de compte Formateur
     * Route: GET /formateur/create-student
     */
    public function createStudent() {
        $this->checkRole('formateur');
        
        // Récupère la liste des promotions pour le select
        $promotions = $this->getPromotions();
        
        require_once __DIR__ . '/../views/formateur/create_student.php';
    }
    
    /**
     * SCRUM-14: Traite la création d'un compte Formateur
     * Route: POST /formateur/store-student
     */
    public function storeStudent() {
        $this->checkRole('formateur');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Récupération et nettoyage des données
            $nom = trim($_POST['nom'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $promotion_id = $_POST['promotion_id'] ?? '';
            $annee = $_POST['annee'] ?? date('Y');
            
            // Validation des données
            $errors = [];
            
            if (empty($nom)) {
                $errors[] = "Le nom est requis";
            }
            
            if (empty($prenom)) {
                $errors[] = "Le prénom est requis";
            }
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Email invalide";
            } else {
                // Vérifie que l'email n'existe pas déjà
                if ($this->emailExists($email)) {
                    $errors[] = "Cet email est déjà utilisé";
                }
            }
            
            if (empty($promotion_id)) {
                $errors[] = "Veuillez sélectionner une promotion";
            }
            
            if (!empty($errors)) {
                $_SESSION['errors'] = $errors;
                $_SESSION['old_input'] = $_POST;
                header('Location: /formateur/create-student');
                exit;
            }
            
            // Génération d'un mot de passe temporaire
            $password_temp = $this->generatePassword();
            $password_hash = password_hash($password_temp, PASSWORD_DEFAULT);
            
            // Insertion dans la base de données
            $studentId = $this->insertStudent([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'password' => $password_hash,
                'promotion_id' => $promotion_id,
                'annee' => $annee,
                'role' => 'etudiant'
            ]);
            
            if ($studentId) {
                // TODO: Envoyer un email avec le mot de passe temporaire
                // $this->sendCredentialsEmail($email, $password_temp);
                
                $_SESSION['success'] = "Étudiant créé avec succès. Email envoyé à : " . $email;
                header('Location: /formateur/students');
            } else {
                $_SESSION['error'] = "Erreur lors de la création de l'étudiant";
                header('Location: /formateur/create-student');
            }
            exit;
        }
    }
    
    /**
     * SCRUM-15: Affiche le formulaire de création de promotion
     * Route: GET /formateur/create-promotion
     */
    public function createPromotion() {
        $this->checkRole('formateur');
        require_once __DIR__ . '/../views/formateur/create_promotion.php';
    }
    
    /**
     * SCRUM-15: Traite la création d'une promotion
     * Route: POST /formateur/store-promotion
     */
    public function storePromotion() {
        $this->checkRole('formateur');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nom_promotion = trim($_POST['nom_promotion'] ?? '');
            $annee = $_POST['annee'] ?? date('Y');
            
            // Validation
            if (empty($nom_promotion)) {
                $_SESSION['error'] = "Le nom de la promotion est requis";
                header('Location: /formateur/create-promotion');
                exit;
            }
            
            // Vérifie si la promotion existe déjà pour cette année
            if ($this->promotionExists($nom_promotion, $annee)) {
                $_SESSION['error'] = "Cette promotion existe déjà pour l'année " . $annee;
                header('Location: /formateur/create-promotion');
                exit;
            }
            
            // Insertion dans la base de données
            $promotionId = $this->insertPromotion([
                'nom' => $nom_promotion,
                'annee' => $annee,
                'createur_id' => $_SESSION['user_id']
            ]);
            
            if ($promotionId) {
                $_SESSION['success'] = "Promotion créée avec succès";
                header('Location: /formateur/promotions');
            } else {
                $_SESSION['error'] = "Erreur lors de la création de la promotion";
                header('Location: /formateur/create-promotion');
            }
            exit;
        }
    }
    
    /**
     * SCRUM-16: Affiche le formulaire de création d'étudiant dans une promotion donnée
     * Route: GET /formateur/promotion/{id}/create-student
     */
    public function createStudentInPromotion($promotion_id) {
        $this->checkRole('formateur');
        
        // Récupère les informations de la promotion
        $promotion = $this->getPromotionById($promotion_id);
        
        if (!$promotion) {
            $_SESSION['error'] = "Promotion introuvable";
            header('Location: /formateur/promotions');
            exit;
        }
        
        require_once __DIR__ . '/../views/formateur/create_student_in_promotion.php';
    }
    
    /**
     * SCRUM-16: Traite la création d'un étudiant dans une promotion donnée
     * Route: POST /formateur/store-student-in-promotion
     */
    public function storeStudentInPromotion() {
        $this->checkRole('formateur');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $promotion_id = $_POST['promotion_id'] ?? '';
            $nom = trim($_POST['nom'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $annee = $_POST['annee'] ?? date('Y');
            
            // Validation
            $errors = [];
            
            if (empty($promotion_id)) {
                $errors[] = "ID de promotion manquant";
            }
            
            if (empty($nom) || empty($prenom) || empty($email)) {
                $errors[] = "Tous les champs sont requis";
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Email invalide";
            } else if ($this->emailExists($email)) {
                $errors[] = "Cet email est déjà utilisé";
            }
            
            if (!empty($errors)) {
                $_SESSION['errors'] = $errors;
                header('Location: /formateur/promotion/' . $promotion_id . '/create-student');
                exit;
            }
            
            // Génération du mot de passe temporaire
            $password_temp = $this->generatePassword();
            $password_hash = password_hash($password_temp, PASSWORD_DEFAULT);
            
            // Insertion de l'étudiant
            $studentId = $this->insertStudent([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'password' => $password_hash,
                'promotion_id' => $promotion_id,
                'annee' => $annee,
                'role' => 'etudiant'
            ]);
            
            if ($studentId) {
                $_SESSION['success'] = "Étudiant créé et ajouté à la promotion avec succès";
                header('Location: /formateur/promotion/' . $promotion_id);
            } else {
                $_SESSION['error'] = "Erreur lors de la création de l'étudiant";
                header('Location: /formateur/promotion/' . $promotion_id . '/create-student');
            }
            exit;
        }
    }
    
    /**
     * SCRUM-17: Affiche le formulaire d'ajout d'étudiant existant à une promotion
     * Route: GET /formateur/add-student-to-promotion
     */
    public function addStudentToPromotion() {
        $this->checkRole('formateur');
        
        // Récupère la liste des étudiants sans promotion ou avec promotion différente
        $etudiants = $this->getAvailableStudents();
        
        // Récupère la liste des promotions
        $promotions = $this->getPromotions();
        
        require_once __DIR__ . '/../views/formateur/add_student_to_promotion.php';
    }
    
    /**
     * SCRUM-17: Traite l'ajout d'un étudiant existant à une promotion
     * Route: POST /formateur/assign-student-to-promotion
     */
    public function assignStudentToPromotion() {
        $this->checkRole('formateur');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $etudiant_id = $_POST['etudiant_id'] ?? '';
            $promotion_id = $_POST['promotion_id'] ?? '';
            
            // Validation
            if (empty($etudiant_id) || empty($promotion_id)) {
                $_SESSION['error'] = "Veuillez sélectionner un étudiant et une promotion";
                header('Location: /formateur/add-student-to-promotion');
                exit;
            }
            
            // Vérifie que l'étudiant n'est pas déjà dans cette promotion
            if ($this->isStudentInPromotion($etudiant_id, $promotion_id)) {
                $_SESSION['error'] = "Cet étudiant est déjà dans cette promotion";
                header('Location: /formateur/add-student-to-promotion');
                exit;
            }
            
            // Assigne l'étudiant à la promotion
            $success = $this->assignStudent($etudiant_id, $promotion_id);
            
            if ($success) {
                $_SESSION['success'] = "Étudiant ajouté à la promotion avec succès";
                header('Location: /formateur/promotion/' . $promotion_id);
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout de l'étudiant à la promotion";
                header('Location: /formateur/add-student-to-promotion');
            }
            exit;
        }
    }
    
    /**
     * Affiche la liste des étudiants
     * Route: GET /formateur/students
     */
    public function listStudents() {
        $this->checkRole('formateur');
        
        // TODO: Récupérer la liste des étudiants depuis la BD
        $etudiants = [];
        
        require_once __DIR__ . '/../views/formateur/students_list.php';
    }
    
    /**
     * Affiche la liste des promotions
     * Route: GET /formateur/promotions
     */
    public function listPromotions() {
        $this->checkRole('formateur');
        
        $promotions = $this->getPromotions();
        
        require_once __DIR__ . '/../views/formateur/promotions_list.php';
    }
    
    /**
     * Affiche les détails d'une promotion
     * Route: GET /formateur/promotion/{id}
     */
    public function viewPromotion($id) {
        $this->checkRole('formateur');
        
        $promotion = $this->getPromotionById($id);
        
        if (!$promotion) {
            $_SESSION['error'] = "Promotion introuvable";
            header('Location: /formateur/promotions');
            exit;
        }
        
        // Récupère les étudiants de cette promotion
        $etudiants = $this->getStudentsByPromotion($id);
        
        require_once __DIR__ . '/../views/formateur/promotion_view.php';
    }
    
    // ==================== MÉTHODES PRIVÉES ====================
    
    /**
     * Vérifie que l'utilisateur a le bon rôle
     */
    private function checkRole($required_role) {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $required_role) {
            $_SESSION['error'] = "Accès non autorisé";
            header('Location: /login');
            exit;
        }
    }
    
    /**
     * Récupère la liste des promotions
     */
    private function getPromotions() {
        // TODO: Requête SQL
        /*
        $query = "SELECT * FROM promotions ORDER BY annee DESC, nom ASC";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        */
        
        // Données de test
        return [
            ['id' => 1, 'nom' => 'Licence 3 Informatique', 'annee' => 2024],
            ['id' => 2, 'nom' => 'Master 1 Data Science', 'annee' => 2024],
            ['id' => 3, 'nom' => 'Licence 2 Mathématiques', 'annee' => 2024]
        ];
    }
    
    /**
     * Récupère une promotion par son ID
     */
    private function getPromotionById($id) {
        // TODO: Requête SQL
        /*
        $query = "SELECT * FROM promotions WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        */
        
        $promotions = $this->getPromotions();
        foreach ($promotions as $promo) {
            if ($promo['id'] == $id) {
                return $promo;
            }
        }
        return null;
    }
    
    /**
     * Vérifie si un email existe déjà
     */
    private function emailExists($email) {
        // TODO: Requête SQL
        /*
        $query = "SELECT COUNT(*) FROM users WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['email' => $email]);
        return $stmt->fetchColumn() > 0;
        */
        return false;
    }
    
    /**
     * Vérifie si une promotion existe
     */
    private function promotionExists($nom, $annee) {
        // TODO: Requête SQL
        /*
        $query = "SELECT COUNT(*) FROM promotions WHERE nom = :nom AND annee = :annee";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['nom' => $nom, 'annee' => $annee]);
        return $stmt->fetchColumn() > 0;
        */
        return false;
    }
    
    /**
     * Insère un nouvel étudiant
     */
    private function insertStudent($data) {
        // TODO: Requête SQL
        /*
        $query = "INSERT INTO users (nom, prenom, email, password, role, promotion_id, annee, created_at) 
                  VALUES (:nom, :prenom, :email, :password, :role, :promotion_id, :annee, NOW())";
        $stmt = $this->db->prepare($query);
        $stmt->execute($data);
        return $this->db->lastInsertId();
        */
        return rand(1, 1000); // Simulation
    }
    
    /**
     * Insère une nouvelle promotion
     */
    private function insertPromotion($data) {
        // TODO: Requête SQL
        /*
        $query = "INSERT INTO promotions (nom, annee, createur_id, created_at) 
                  VALUES (:nom, :annee, :createur_id, NOW())";
        $stmt = $this->db->prepare($query);
        $stmt->execute($data);
        return $this->db->lastInsertId();
        */
        return rand(1, 1000); // Simulation
    }
    
    /**
     * Récupère les étudiants disponibles (sans promotion ou changeables)
     */
    private function getAvailableStudents() {
        // TODO: Requête SQL
        /*
        $query = "SELECT * FROM users WHERE role = 'etudiant' ORDER BY nom, prenom";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        */
        return [];
    }
    
    /**
     * Vérifie si un étudiant est déjà dans une promotion
     */
    private function isStudentInPromotion($etudiant_id, $promotion_id) {
        // TODO: Requête SQL
        /*
        $query = "SELECT COUNT(*) FROM users WHERE id = :etudiant_id AND promotion_id = :promotion_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['etudiant_id' => $etudiant_id, 'promotion_id' => $promotion_id]);
        return $stmt->fetchColumn() > 0;
        */
        return false;
    }
    
    /**
     * Assigne un étudiant à une promotion
     */
    private function assignStudent($etudiant_id, $promotion_id) {
        // TODO: Requête SQL
        /*
        $query = "UPDATE users SET promotion_id = :promotion_id WHERE id = :etudiant_id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['etudiant_id' => $etudiant_id, 'promotion_id' => $promotion_id]);
        */
        return true;
    }
    
    /**
     * Récupère les étudiants d'une promotion
     */
    private function getStudentsByPromotion($promotion_id) {
        // TODO: Requête SQL
        /*
        $query = "SELECT * FROM users WHERE promotion_id = :promotion_id AND role = 'etudiant' ORDER BY nom, prenom";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['promotion_id' => $promotion_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        */
        return [];
    }
    
    /**
     * Génère un mot de passe aléatoire sécurisé
     */
    private function generatePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
    }
    
    /**
     * Compte le nombre total d'étudiants
     */
    private function countEtudiants() {
        // TODO: Requête SQL
        return 0;
    }
    
    /**
     * Compte le nombre total de promotions
     */
    private function countPromotions() {
        // TODO: Requête SQL
        return 0;
    }
    
    /**
     * Compte le nombre d'espaces pédagogiques
     */
    private function countEspacesPedagogiques() {
        // TODO: Requête SQL
        return 0;
    }
    
    /**
     * Compte les nouveaux étudiants (ce mois)
     */
    private function countNouveauxEtudiants() {
        // TODO: Requête SQL
        return 0;
    }
}
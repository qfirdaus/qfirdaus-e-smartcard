<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ListPermohonan.php';

class ListPermohonanController
{
    public array $senaraiPermohonan = [];
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance('mysql')->getConnection();

        $model = new ListPermohonan($this->pdo);

        $stafID = $_SESSION['f_stafID'] ?? null;

        if($stafID){

            $this->senaraiPermohonan = $model->getByStaf($stafID);

        }else{

            $this->senaraiPermohonan = [];

        }

    }
}
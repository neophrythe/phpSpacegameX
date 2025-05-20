<?php
namespace Models;

use Core\Model;
use PDO;

class ResearchType extends Model {
    public $id;
    public $internal_name;
    public $name_de;
    public $name_en;
    public $description_de;
    public $description_en;
    public $base_cost_eisen;
    public $base_cost_silber;
    public $base_cost_uderon;
    public $base_cost_wasserstoff;
    public $base_cost_energie;
    public $cost_factor;
    public $base_research_time;
    public $research_time_factor;
    public $requirements_json;

    public static function getAll() {
        $db = self::getDB();
        $stmt = $db->query('SELECT * FROM static_research_types');
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }

    public static function getById($id) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM static_research_types WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function getByInternalName($internalName) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM static_research_types WHERE internal_name = :internal_name');
        $stmt->bindParam(':internal_name', $internalName, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }
    
    // Calculate the cost to research this technology at a specific level
    public function getCostAtLevel($level) {
        return [
            'eisen' => $this->base_cost_eisen * pow($this->cost_factor, $level),
            'silber' => $this->base_cost_silber * pow($this->cost_factor, $level),
            'uderon' => $this->base_cost_uderon * pow($this->cost_factor, $level),
            'wasserstoff' => $this->base_cost_wasserstoff * pow($this->cost_factor, $level),
            'energie' => $this->base_cost_energie * pow($this->cost_factor, $level),
        ];
    }

    // Calculate the research time for this technology at a specific level, considering research lab level
    public function getResearchTimeAtLevel($level, $labLevel) {
        // Base research time increases with level of the research itself
        $baseTime = $this->base_research_time * pow($this->research_time_factor, $level);

        // If calculating time for the first level (current level of research is 0), 
        // the Research Lab level does not reduce the base research time for this first level.
        if ($level == 0) {
            $adjustedTime = $baseTime;
        } else {
            // For subsequent levels, research time is affected by Research Lab level.
            // Assuming a simple inverse relationship as per manual.md: "Ausbaustufe des Forschungszentrums ist für die Forschungszeit verantwortlich."
            // Needs refinement based on actual game mechanics if available.
            $adjustedTime = $baseTime / ($labLevel > 0 ? $labLevel : 1); // Avoid division by zero
        }

        return ceil($adjustedTime); // Return time in seconds, rounded up
    }
}
?>

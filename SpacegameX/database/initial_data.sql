-- Insert initial building types
INSERT INTO static_building_types (
    internal_name, name_de, name_en, description_de, description_en, 
    base_cost_eisen, base_cost_silber, base_cost_uderon, base_cost_wasserstoff, base_cost_energie, cost_factor, 
    base_production_eisen, base_production_silber, base_production_uderon, base_production_wasserstoff,
    base_production_energie, base_consumption_wasserstoff, base_consumption_energie, 
    base_build_time, build_time_factor, max_level, requirements_json
) VALUES 
-- Resource Production Buildings
(
    'eisenmine', 'Eisenmine', 'Metal Mine', 
    'Eisenminen fördern das wertvolle Metall aus tiefen Minen. Je höher die Stufe, desto mehr Eisen kann gefördert werden.',
    'Metal mines extract valuable metal from deep mines. Higher levels allow more metal to be extracted.',
    100, 40, 0, 0, 0, 1.5, 
    30, 0, 0, 0, 
    0, 0, 10, -- base_consumption_energie is 10
    100, 1.5, 40, NULL
),
(
    'silbermine', 'Silbermine', 'Crystal Mine',
    'Silber ist der wichtigste Rohstoff für elektronische Komponenten und Legierungen.',
    'Crystal is the main resource used for electronics and alloys.',
    150, 60, 0, 0, 0, 1.6, 
    0, 20, 0, 0, 
    0, 0, 10, -- base_consumption_energie is 10
    160, 1.5, 40, NULL
),
(
    'wasserstoff_raffinerie', 'Wasserstoff-Raffinerie', 'Hydrogen Synthesizer', -- Renamed internal_name from deuterium_synthesizer
    'Wasserstoff (H2) wird als Treibstoff für Schiffe und für fortschrittliche Forschungen benötigt.',
    'Hydrogen (H2) is used as fuel for ships and for advanced research.',
    350, 160, 0, 0, 0, 1.5, 
    0, 0, 0, 10, 
    0, 0, 20, -- base_consumption_energie is 20
    200, 1.5, 30, NULL
),
(
    'uderon_raffinerie', 'Uderon-Raffinerie', 'Uderon Refinery',
    'In der Uderon-Raffinerie wird der kostbare Rohstoff Uderon veredelt. Wird für Bau- und Forschungsvorhaben benötigt.',
    'The Uderon refinery processes the valuable resource Uderon. Required for construction and research projects.',
    300, 150, 30, 0, 0, 1.5, 
    0, 0, 5, 0, -- Assuming some base uderon production for a refinery, placeholder
    0, 0, 15, -- base_consumption_energie is 15
    180, 1.5, 30, NULL
),

-- Energy Buildings
(
    'sonnenkollektor', 'Sonnenkollektor', 'Solar Plant', -- Renamed internal_name from solar_plant
    'Die Solaranlage gewinnt Energie aus der Sonnenstrahlung. Wird für die Betreibung von Minen benötigt.',
    'Solar plants collect energy from solar radiation. Energy is needed for operating mines.',
    150, 80, 0, 0, 0, 1.5, 
    0, 0, 0, 0, 
    20, 0, 0, -- base_production_energie is 20
    150, 1.5, 30, NULL
),
(
    'fusionskraftwerk', 'Fusionskraftwerk', 'Fusion Reactor', -- Renamed internal_name from fusion_reactor
    'Gewinnt große Mengen an Energie aus der Fusion von Wasserstoff (H2), verbraucht aber auch Wasserstoff.',
    'Generates large amounts of energy from hydrogen fusion, but also consumes hydrogen.',
    1000, 500, 0, 300, 0, 1.8, 
    0, 0, 0, 0, 
    50, 10, 0, -- base_production_energie is 50, base_consumption_wasserstoff is 10
    300, 1.5, 20, NULL
),

-- Infrastructure Buildings
(
    'roboterfabrik', 'Roboterfabrik', 'Robot Factory', -- Renamed internal_name from robot_factory
    'Die Roboterfabrik stellt einfache Arbeitskräfte her, die zum Bau der planetaren Infrastruktur genutzt werden können. Mit jeder Stufe wird die Baugeschwindigkeit erhöht.',
    'The robot factory produces simple workers that can help build planetary structures. Each level speeds up construction time.',
    400, 200, 0, 100, 0, 2.0, 
    0, 0, 0, 0, 
    0, 0, 0, 
    200, 1.5, 10, NULL -- Default build_time_factor from schema
),
(
    'werft', 'Schiffswerft', 'Shipyard', -- Renamed internal_name from shipyard
    'In der Schiffswerft können alle Arten von Schiffen und Verteidigungsanlagen gebaut werden.',
    'All types of ships and defense systems can be constructed at the shipyard.',
    600, 400, 0, 200, 0, 2.0, 
    0, 0, 0, 0, 
    0, 0, 0, 
    300, 1.5, 12, NULL -- Default build_time_factor from schema
),
(
    'forschungszentrum', 'Forschungslabor', 'Research Lab', -- Renamed internal_name from research_lab
    'Im Forschungslabor werden neue Technologien entwickelt. Höhere Stufen ermöglichen fortschrittlichere Forschung.',
    'New technologies are developed in the research lab. Higher levels enable more advanced research.',
    400, 300, 0, 200, 0, 2.0, 
    0, 0, 0, 0, 
    0, 0, 0, 
    200, 1.5, 12, NULL -- Default build_time_factor from schema
),
(
    'allianzdepot', 'Allianzdepot', 'Alliance Depot', -- Renamed internal_name from alliance_depot
    'Das Allianzdepot bietet zusätzlichen Lagerraum für Rohstoffe und ermöglicht das Stationieren von Allianzschiffen.',
    'The alliance depot provides additional storage for resources and allows alliance ships to be stationed.',
    1000, 1000, 0, 100, 0, 2.0, 
    0, 0, 0, 0, 
    0, 0, 0, 
    500, 1.5, 5, NULL -- Default build_time_factor from schema
),
(
    'raketensilo', 'Raketensilo', 'Missile Silo', -- Renamed internal_name from missile_silo
    'Raketensilo dienen zur Lagerung von Abwehrraketen und Interplanetarraketen.',
    'Missile silos store both defense and interplanetary missiles.',
    2000, 4000, 0, 1000, 0, 2.0, 
    0, 0, 0, 0, 
    0, 0, 0, 
    400, 1.5, 5, NULL -- Default build_time_factor from schema
),
-- Anti-Spionage-Schild
(
    'anti_spionage_schild', 'Anti-Spionage-Schild', NULL,
    'Das Anti-Spionage-Schild dient der Abwehr von Sonden. Zum einen liefern sie keinen so genauen Spionagebericht mehr, zum anderen kann es je nach Ausbaustufe vor Angriffen durch Sonden schützen. Dies bedeutet, das bei einem Angriff ein bestimmter Prozentsatz der Sonden zerstört wird.',
    NULL,
    6500, 4000, 5000, 1800, 1500, 1.4,
    0, 0, 0, 0,  -- No production
    0, 0, 0,     -- No consumption by default
    2700, 1.5, 20,
    '{"building": {"zentrale": 10, "eisenmine": 9, "fusionskraftwerk": 15}}'
);

-- Add requirements (as an example)
UPDATE static_building_types 
SET requirements_json = '{"buildings": {"eisenmine": 5, "silbermine": 3}}'
WHERE internal_name = 'wasserstoff_raffinerie';

UPDATE static_building_types 
SET requirements_json = '{"buildings": {"eisenmine": 10, "silbermine": 10, "wasserstoff_raffinerie": 5}}'
WHERE internal_name = 'fusionskraftwerk';

UPDATE static_building_types 
SET requirements_json = '{"buildings": {"eisenmine": 2}}'
WHERE internal_name = 'sonnenkollektor';

-- Insert initial research types
INSERT INTO static_research_types (
    internal_name, name_de, name_en, description_de, description_en, 
    base_cost_eisen, base_cost_silber, base_cost_wasserstoff, cost_factor, -- Changed metal to eisen, crystal to silber, h2 to wasserstoff
    research_time_factor, requirements_json
) VALUES 
-- Energy Research
(
    'energy_technology', 'Energietechnik', 'Energy Technology', 
    'Die Energietechnik befasst sich mit der Weiterentwicklung der Energiesysteme und Energiespeicherung.',
    'Energy technology deals with the advancement of energy systems and energy storage.',
    800, 400, 100, 2.0, 300, NULL -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
),
(
    'laser_technology', 'Lasertechnik', 'Laser Technology',
    'Die Lasertechnik ist eine wichtige Grundlage für die Entwicklung von fortschrittlichen Waffensystemen.',
    'Laser technology is an important basis for the development of advanced weapon systems.',
    300, 100, 50, 2.0, 250, '{"research": {"energy_technology": 2}}' -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
),

-- Drive Research
(
    'combustion_drive', 'Verbrennungsantrieb', 'Combustion Drive',
    'Der Verbrennungsantrieb basiert auf dem uralten Prinzip des Rückstoßes.',
    'The combustion drive is based on the ancient principle of recoil.',
    400, 200, 100, 2.0, 200, NULL -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
),
(
    'ion_drive', 'Ionenantrieb', 'Ion Drive',
    'Der Ionenantrieb beschleunigt Ionen und erzeugt dadurch Schub für Raumschiffe.',
    'The ion drive accelerates ions and thereby generates thrust for spaceships.',
    1000, 800, 400, 2.0, 400, '{"research": {"energy_technology": 4, "combustion_drive": 3}}' -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
),
(
    'hyperdrive', 'Hyperraumantrieb', 'Hyperdrive',
    'Der Hyperraumantrieb erlaubt es, durch Krümmung des Raumes große Distanzen schnell zu überwinden.',
    'The hyperdrive allows travel across vast distances by bending space.',
    4000, 3000, 1500, 2.0, 800, '{"research": {"energy_technology": 8, "ion_drive": 5}}' -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
),

-- Weapons Research
(
    'weapons_technology', 'Waffentechnik', 'Weapons Technology',
    'Die Weiterentwicklung von Waffen erhöht den Schaden deiner Kampfschiffe.',
    'Advanced weapons technology increases the damage output of your combat ships.',
    800, 500, 200, 2.0, 350, '{"research": {"energy_technology": 3}}' -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
),
(
    'shielding_technology', 'Schildtechnik', 'Shielding Technology',
    'Schildtechnik verbessert die Effektivität der Energieschilde deiner Schiffe und Verteidigungsanlagen.',
    'Shielding technology improves the effectiveness of energy shields for your ships and defense facilities.',
    600, 800, 400, 2.0, 400, '{"research": {"energy_technology": 3}}' -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
),
(
    'armor_technology', 'Panzerung', 'Armor Technology',
    'Die Forschung in Panzerungstechnologie verbessert die strukturelle Integrität deiner Raumschiffe.',
    'Research in armor technology improves the structural integrity of your spaceships.',
    1000, 500, 100, 2.0, 300, NULL -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
),

-- Production/Economy Research
(
    'mining_technology', 'Fördertechnik', 'Mining Technology',
    'Verbessert die Effizienz deiner Minen und erhöht deren Produktion.',
    'Improves the efficiency of your mines and increases their production.',
    500, 300, 100, 2.0, 250, NULL -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
),
(
    'computer_technology', 'Computertechnik', 'Computer Technology',
    'Fortschritte in der Computertechnik ermöglichen dir, mehr Flotten gleichzeitig zu kontrollieren.',
    'Advances in computer technology allow you to control more fleets simultaneously.',
    200, 600, 100, 2.0, 300, NULL -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
),
(
    'espionage_technology', 'Spionagetechnik', 'Espionage Technology',
    'Mit Verbesserungen in der Spionagetechnik kannst du mehr Informationen über andere Spieler sammeln.',
    'With improvements in espionage technology, you can gather more information about other players.',
    300, 500, 200, 2.0, 350, '{"research": {"computer_technology": 2}}' -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
),

-- Advanced Research
(
    'graviton_technology', 'Gravitontechnik', 'Graviton Technology',
    'Gravitontechnik ermöglicht die Manipulation der Schwerkraft und die Entwicklung spezieller Waffen.',
    'Graviton technology allows the manipulation of gravity and the development of special weapons.',
    10000, 8000, 5000, 2.5, 1600, '{"research": {"energy_technology": 12, "shielding_technology": 8, "hyperdrive": 6}}' -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
),
(
    'intergalactic_research_network', 'Intergalaktisches Forschungsnetzwerk', 'Intergalactic Research Network',
    'Verbindet die Labore aller deiner Planeten für schnellere Forschung.',
    'Connects laboratories across all your planets for faster research.',
    5000, 7000, 2000, 2.2, 1200, '{"research": {"computer_technology": 8, "hyperdrive": 5}}' -- Assuming base_cost_h2 was intended for base_cost_wasserstoff
);

-- Add specific requirements
UPDATE static_research_types 
SET requirements_json = '{"buildings": {"research_lab": 1}}'
WHERE internal_name = 'energy_technology';

UPDATE static_research_types 
SET requirements_json = '{"buildings": {"research_lab": 2}}'
WHERE internal_name = 'mining_technology';

UPDATE static_research_types 
SET requirements_json = '{"buildings": {"research_lab": 2}}'
WHERE internal_name = 'combustion_drive';

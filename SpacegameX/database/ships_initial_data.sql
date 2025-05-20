-- Initial ship types for SpacegameX
-- Add this to initial_data.sql

-- Insert ship types
INSERT INTO static_ship_types (
    internal_name, name_de, name_en, description_de, description_en, 
    base_cost_eisen, base_cost_silber, base_cost_wasserstoff, base_build_time, -- Changed metal to eisen, crystal to silber, h2 to wasserstoff
    speed, cargo_capacity, fuel_consumption, weapon_power, shield_power, hull_strength,
    requirements_json
) VALUES
-- Small Ships
(
    'small_cargo', 'Kleiner Transporter', 'Small Cargo', 
    'Der kleine Transporter ist ein wendiges Schiff, das Ressourcen schnell zwischen Planeten transportieren kann.',
    'The small cargo ship is an agile vessel used to quickly transport resources between planets.',
    2000, 2000, 0, 1800, 
    5000, 5000, 10, 5, 10, 400,
    '{"buildings": {"shipyard": 2}, "research": {"combustion_drive": 2}}'
),
(
    'large_cargo', 'Großer Transporter', 'Large Cargo', 
    'Der große Transporter kann deutlich mehr Rohstoffe transportieren als der kleine Transporter, ist dafür aber langsamer.',
    'The large cargo ship can transport significantly more resources than the small cargo, but is slower.',
    6000, 6000, 0, 7200, 
    7500, 25000, 50, 5, 25, 1200,
    '{"buildings": {"shipyard": 4}, "research": {"combustion_drive": 6}}'
),
(
    'light_fighter', 'Leichter Jäger', 'Light Fighter', 
    'Der leichte Jäger ist ein wendiges aber schwach gepanzertes Kampfschiff.',
    'The light fighter is an agile but lightly armored combat ship.',
    3000, 1000, 0, 1800, 
    12500, 50, 20, 50, 10, 400,
    '{"buildings": {"shipyard": 1}, "research": {"combustion_drive": 1}}'
),
(
    'heavy_fighter', 'Schwerer Jäger', 'Heavy Fighter', 
    'Die Weiterentwicklung des leichten Jägers mit stärkerer Panzerung und Bewaffnung.',
    'An evolution of the light fighter with stronger armor and weaponry.',
    6000, 4000, 0, 3600, 
    10000, 100, 75, 150, 25, 1000,
    '{"buildings": {"shipyard": 3}, "research": {"ion_drive": 2}, "research": {"weapons_technology": 3}}'
),

-- Medium Ships
(
    'cruiser', 'Kreuzer', 'Cruiser', 
    'Kreuzer sind stark bewaffnet und gepanzert und daher ideal für den Kampf gegen leichte Jäger.',
    'Cruisers are heavily armed and armored and therefore ideal for combat against light fighters.',
    20000, 7000, 2000, 7200, 
    15000, 800, 300, 400, 50, 2700,
    '{"buildings": {"shipyard": 5}, "research": {"ion_drive": 4}, "research": {"weapons_technology": 5}}'
),
(
    'battleship', 'Schlachtschiff', 'Battleship', 
    'Das Schlachtschiff ist ein schwer bewaffneter und gepanzerter Allrounder.',
    'The battleship is a heavily armed and armored all-rounder.',
    45000, 15000, 5000, 14400, 
    10000, 1500, 500, 1000, 200, 6000,
    '{"buildings": {"shipyard": 7}, "research": {"hyperdrive": 4}}'
),

-- Special Ships
(
    'colony_ship', 'Kolonieschiff', 'Colony Ship', 
    'Mit dem Kolonieschiff können neue Planeten besiedelt werden.',
    'The colony ship is used to settle new planets.',
    10000, 20000, 10000, 10800, 
    2500, 7500, 1000, 50, 100, 3000,
    '{"buildings": {"shipyard": 4}, "research": {"ion_drive": 3}}'
),
(
    'espionage_probe', 'Spionagesonde', 'Espionage Probe', 
    'Spionagesonden sind kleine, wendige Schiffe, die zur Informationsbeschaffung dienen.',
    'Espionage probes are small, agile ships used to gather information.',
    0, 1000, 0, 300, 
    100000000, 5, 1, 0, 0, 100, 
    '{"buildings": {"shipyard": 3}, "research": {"espionage_technology": 2}}'
),
(
    'bomber', 'Bomber', 'Bomber', 
    'Der Bomber ist darauf spezialisiert, planetare Verteidigungsstrukturen zu zerstören.',
    'The bomber specializes in destroying planetary defense structures.',
    50000, 25000, 15000, 21600, 
    4000, 500, 1000, 1000, 500, 7500,
    '{"buildings": {"shipyard": 8}, "research": {"hyperdrive": 6}, "research": {"weapons_technology": 8}}'
),
(
    'destroyer', 'Zerstörer', 'Destroyer', 
    'Zerstörer sind große Kampfschiffe, die sich auf die Bekämpfung von Flotten spezialisiert haben.',
    'Destroyers are large warships specialized in combating fleets.',
    60000, 50000, 15000, 28800, 
    5000, 2000, 1000, 2000, 500, 11000,
    '{"buildings": {"shipyard": 9}, "research": {"hyperdrive": 6}, "research": {"weapons_technology": 10}}'
),
(
    'deathstar', 'Todesstern', 'Death Star', 
    'Der Todesstern ist ein gigantisches Kampfschiff, das sogar ganze Monde zerstören kann.',
    'The Death Star is a gigantic warship capable of destroying entire moons.',
    5000000, 4000000, 1000000, 172800, 
    100, 1000000, 100000, 200000, 50000, 900000,
    '{"buildings": {"shipyard": 12}, "research": {"graviton_technology": 1}, "research": {"hyperdrive": 7}}'
),
(
    'recycler', 'Recycler', 'Recycler', 
    'Der Recycler dient zum Sammeln von Trümmerfeldern, die nach Kämpfen im Orbit entstehen.',
    'The recycler is used to collect debris fields that form in orbit after battles.',
    10000, 6000, 2000, 10800, 
    2000, 20000, 300, 1, 10, 2500,
    '{"buildings": {"shipyard": 4}, "research": {"combustion_drive": 6}, "research": {"shielding_technology": 2}}'
);

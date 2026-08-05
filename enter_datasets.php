<?php
// ===== CONFIGURACIÓN =====
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$db_config = [
    'host' => 'localhost',
    'dbname' => 'u303404040_BASE_ACV_LCA',
    'username' => 'u303404040_IvanMendoza',  // CAMBIAR
    'password' => '*hba3Qn=',  // CAMBIAR
    'charset' => 'utf8mb4'
];

// ===== MAPEO ISIC → SECTOR PRINCIPAL =====
// Cuando el ISIC está claro pero sector_principal no, usar este mapeo
$mapeo_isic_a_principal = [
    'A' => 'Alimentos',           // Agricultura → Alimentos
    'B' => 'Construccion',        // Minería → Construcción (materiales)
    'C' => null,                  // Manufactura → depende del subtipo
    'D' => 'Energia',             // Electricidad/gas → Energía
    'E' => null,                  // Agua/residuos → dividido
    'F' => 'Construccion',        // Construcción → Construcción
    'G' => null,                  // Comercio → no aplica a los 5 principales
    'H' => 'Energia',             // Transporte → Energía
    'I' => 'Alimentos',           // Alojamiento/comidas → Alimentos
    'J' => 'Energia',             // Información/comunicaciones → Energía (servidores)
    'K' => null,                  // Finanzas → no aplica
    'L' => 'Construccion',        // Inmobiliaria → Construcción
    'M' => null,                  // Profesional → no aplica
    'N' => null,                  // Administrativo → no aplica
    'O' => null,                  // Gobierno → no aplica
    'P' => null,                  // Educación → no aplica
    'Q' => null,                  // Salud → no aplica
    'R' => null,                  // Arte/recreación → no aplica
    'S' => null,                  // Otros servicios → no aplica
    'T' => null,                  // Hogares → no aplica
    'U' => null                   // Extra-territorial → no aplica
];

// ===== MAPEO SUPER EXPANDIDO SECTOR PRINCIPAL =====
$mapeo_directo_sector_principal = [
    // ==================== CONSTRUCCIÓN ====================
    // Construcción directa
    'construcción' => 'Construccion', 'construction' => 'Construccion', 'constructing' => 'Construccion',
    'construir' => 'Construccion', 'construct' => 'Construccion', 'built' => 'Construccion',
    'building' => 'Construccion', 'edificación' => 'Construccion', 'obra' => 'Construccion',
    'edificio' => 'Construccion', 'house' => 'Construccion', 'casa' => 'Construccion',
    'vivienda' => 'Construccion', 'housing' => 'Construccion',
    
    // Materiales de construcción
    'cemento' => 'Construccion', 'cement' => 'Construccion', 'concreto' => 'Construccion',
    'concrete' => 'Construccion', 'hormigón' => 'Construccion', 'ladrillo' => 'Construccion',
    'brick' => 'Construccion', 'bloque' => 'Construccion', 'block' => 'Construccion',
    'mortero' => 'Construccion', 'mortar' => 'Construccion', 'yeso' => 'Construccion',
    'plaster' => 'Construccion', 'gypsum' => 'Construccion',
    
    // Acero y metales construcción
    'acero' => 'Construccion', 'steel' => 'Construccion', 'rebar' => 'Construccion',
    'varilla' => 'Construccion', 'refuerzo' => 'Construccion', 'reinforcement' => 'Construccion',
    'armadura' => 'Construccion', 'reinforced' => 'Construccion',
    
    // Infraestructura
    'infraestructura' => 'Construccion', 'infrastructure' => 'Construccion',
    'puente' => 'Construccion', 'bridge' => 'Construccion', 'túnel' => 'Construccion',
    'tunnel' => 'Construccion', 'carretera' => 'Construccion', 'road' => 'Construccion',
    'highway' => 'Construccion', 'autovía' => 'Construccion', 'viaduct' => 'Construccion',
    'viaducto' => 'Construccion',
    
    // Pavimentos
    'pavimento' => 'Construccion', 'pavement' => 'Construccion', 'paving' => 'Construccion',
    'pavimentación' => 'Construccion', 'asfalto' => 'Construccion', 'asphalt' => 'Construccion',
    'bitumen' => 'Construccion', 'betún' => 'Construccion',
    
    // Minería (produce materiales de construcción)
    'mining' => 'Construccion', 'mina' => 'Construccion', 'minas' => 'Construccion',
    'mine' => 'Construccion', 'quarry' => 'Construccion', 'cantera' => 'Construccion',
    'canteras' => 'Construccion', 'quarrying' => 'Construccion', 'mineral' => 'Construccion',
    'ore' => 'Construccion', 'extracción' => 'Construccion', 'extraction' => 'Construccion',
    
    // Áridos y agregados
    'grava' => 'Construccion', 'gravel' => 'Construccion', 'arena' => 'Construccion',
    'sand' => 'Construccion', 'piedra' => 'Construccion', 'stone' => 'Construccion',
    'aggregate' => 'Construccion', 'áridos' => 'Construccion', 'limestone' => 'Construccion',
    'caliza' => 'Construccion', 'mármol' => 'Construccion', 'marble' => 'Construccion',
    'granite' => 'Construccion', 'granito' => 'Construccion',
    
    // Vidrio (construcción)
    'vidrio' => 'Construccion', 'glass' => 'Construccion', 'cristal' => 'Construccion',
    'window' => 'Construccion', 'ventana' => 'Construccion',
    
    // Madera construcción
    'madera' => 'Construccion', 'wood' => 'Construccion', 'timber' => 'Construccion',
    'lumber' => 'Construccion', 'plywood' => 'Construccion', 'contrachapado' => 'Construccion',
    
    // Inmobiliario
    'inmobiliaria' => 'Construccion', 'real estate' => 'Construccion', 'property' => 'Construccion',
    'propiedad' => 'Construccion',
    
    // ==================== RESIDUOS ====================
    'residuos' => 'Residuos', 'residuo' => 'Residuos', 'waste' => 'Residuos',
    'wastes' => 'Residuos', 'basura' => 'Residuos', 'trash' => 'Residuos',
    'garbage' => 'Residuos', 'rubbish' => 'Residuos', 'desecho' => 'Residuos',
    'disposal' => 'Residuos', 'disposed' => 'Residuos',
    
    // Reciclaje
    'reciclaje' => 'Residuos', 'recycling' => 'Residuos', 'recycle' => 'Residuos',
    'reciclar' => 'Residuos', 'recycled' => 'Residuos', 'reutilización' => 'Residuos',
    'reuse' => 'Residuos', 'reused' => 'Residuos',
    
    // Vertederos e incineración
    'vertedero' => 'Residuos', 'landfill' => 'Residuos', 'relleno sanitario' => 'Residuos',
    'dump' => 'Residuos', 'incineración' => 'Residuos', 'incineration' => 'Residuos',
    'incinerator' => 'Residuos', 'incineradora' => 'Residuos', 'incinerate' => 'Residuos',
    
    // Compostaje
    'compost' => 'Residuos', 'compostaje' => 'Residuos', 'composting' => 'Residuos',
    'organic waste' => 'Residuos', 'residuos orgánicos' => 'Residuos',
    
    // Tipos específicos
    'RSU' => 'Residuos', 'MSW' => 'Residuos', 'municipal solid waste' => 'Residuos',
    'residuos sólidos' => 'Residuos', 'solid waste' => 'Residuos',
    'hazardous waste' => 'Residuos', 'residuos peligrosos' => 'Residuos',
    'e-waste' => 'Residuos', 'electronic waste' => 'Residuos',
    'residuos electrónicos' => 'Residuos', 'scrap' => 'Residuos', 'chatarra' => 'Residuos',
    
    // Gestión
    'gestión de residuos' => 'Residuos', 'waste management' => 'Residuos',
    'tratamiento de residuos' => 'Residuos', 'waste treatment' => 'Residuos',
    
    // ==================== ENERGÍA ====================
    // Energía general
    'energía' => 'Energia', 'energy' => 'Energia', 'energetic' => 'Energia',
    'energético' => 'Energia', 'power' => 'Energia', 'potencia' => 'Energia',
    
    // Electricidad
    'electricidad' => 'Energia', 'electricity' => 'Energia', 'eléctrico' => 'Energia',
    'electric' => 'Energia', 'electrical' => 'Energia', 'electrico' => 'Energia',
    
    // Combustibles
    'combustible' => 'Energia', 'fuel' => 'Energia', 'petróleo' => 'Energia',
    'oil' => 'Energia', 'petroleum' => 'Energia', 'crude' => 'Energia', 'crudo' => 'Energia',
    'diesel' => 'Energia', 'gasolina' => 'Energia', 'gasoline' => 'Energia',
    'petrol' => 'Energia', 'kerosene' => 'Energia', 'queroseno' => 'Energia',
    
    // Gas
    'gas' => 'Energia', 'natural gas' => 'Energia', 'gas natural' => 'Energia',
    'LNG' => 'Energia', 'GNL' => 'Energia', 'propane' => 'Energia', 'propano' => 'Energia',
    'butane' => 'Energia', 'butano' => 'Energia',
    
    // Carbón
    'coal' => 'Energia', 'carbón' => 'Energia', 'carbon' => 'Energia',
    'anthracite' => 'Energia', 'antracita' => 'Energia', 'lignite' => 'Energia',
    'lignito' => 'Energia',
    
    // Renovables
    'solar' => 'Energia', 'photovoltaic' => 'Energia', 'fotovoltaico' => 'Energia',
    'PV' => 'Energia', 'wind' => 'Energia', 'eólica' => 'Energia', 'eolica' => 'Energia',
    'turbine' => 'Energia', 'turbina' => 'Energia', 'windmill' => 'Energia',
    'molino' => 'Energia', 'hidroeléctrica' => 'Energia', 'hydroelectric' => 'Energia',
    'hydro' => 'Energia', 'hydraulic' => 'Energia', 'hidráulica' => 'Energia',
    'geotérmica' => 'Energia', 'geothermal' => 'Energia', 'biomasa' => 'Energia',
    'biomass' => 'Energia', 'biofuel' => 'Energia', 'biocombustible' => 'Energia',
    'biogas' => 'Energia', 'biogás' => 'Energia', 'ethanol' => 'Energia',
    'etanol' => 'Energia', 'biodiesel' => 'Energia',
    
    // Nuclear
    'nuclear' => 'Energia', 'uranium' => 'Energia', 'uranio' => 'Energia',
    'plutonium' => 'Energia', 'plutonio' => 'Energia', 'reactor' => 'Energia',
    
    // Generación
    'generación' => 'Energia', 'generation' => 'Energia', 'generating' => 'Energia',
    'generator' => 'Energia', 'generador' => 'Energia', 'power plant' => 'Energia',
    'central' => 'Energia', 'power station' => 'Energia', 'planta' => 'Energia',
    
    // Transporte (consume energía)
    'transporte' => 'Energia', 'transport' => 'Energia', 'transportation' => 'Energia',
    'freight' => 'Energia', 'carga' => 'Energia', 'logística' => 'Energia',
    'logistics' => 'Energia', 'vehicle' => 'Energia', 'vehículo' => 'Energia',
    'camión' => 'Energia', 'truck' => 'Energia', 'car' => 'Energia', 'automóvil' => 'Energia',
    'ship' => 'Energia', 'barco' => 'Energia', 'aircraft' => 'Energia', 'avión' => 'Energia',
    'train' => 'Energia', 'tren' => 'Energia', 'locomotive' => 'Energia',
    'locomotora' => 'Energia',
    
    // Vapor y calefacción
    'vapor' => 'Energia', 'steam' => 'Energia', 'boiler' => 'Energia', 'caldera' => 'Energia',
    'heating' => 'Energia', 'calefacción' => 'Energia', 'heat' => 'Energia',
    'thermal' => 'Energia', 'térmico' => 'Energia',
    
    // Unidades energéticas
    'kWh' => 'Energia', 'MWh' => 'Energia', 'GWh' => 'Energia', 'joule' => 'Energia',
    'watt' => 'Energia', 'kilowatt' => 'Energia', 'megawatt' => 'Energia',
    'gigawatt' => 'Energia', 'BTU' => 'Energia',
    
    // ==================== ALIMENTOS ====================
    // Agricultura
    'agricultura' => 'Alimentos', 'agriculture' => 'Alimentos', 'agrícola' => 'Alimentos',
    'agricultural' => 'Alimentos', 'agro' => 'Alimentos', 'farm' => 'Alimentos',
    'farming' => 'Alimentos', 'granja' => 'Alimentos', 'ranch' => 'Alimentos',
    
    // Cultivos
    'cultivo' => 'Alimentos', 'crop' => 'Alimentos', 'cultivation' => 'Alimentos',
    'harvest' => 'Alimentos', 'cosecha' => 'Alimentos', 'siembra' => 'Alimentos',
    'planting' => 'Alimentos', 'seed' => 'Alimentos', 'semilla' => 'Alimentos',
    
    // Ganadería
    'ganadería' => 'Alimentos', 'livestock' => 'Alimentos', 'cattle' => 'Alimentos',
    'ganado' => 'Alimentos', 'vacuno' => 'Alimentos', 'bovino' => 'Alimentos',
    'bovine' => 'Alimentos', 'beef' => 'Alimentos', 'res' => 'Alimentos',
    'vaca' => 'Alimentos', 'cow' => 'Alimentos', 'cerdo' => 'Alimentos',
    'pig' => 'Alimentos', 'pork' => 'Alimentos', 'porcino' => 'Alimentos',
    'swine' => 'Alimentos', 'pollo' => 'Alimentos', 'chicken' => 'Alimentos',
    'poultry' => 'Alimentos', 'avicultura' => 'Alimentos', 'ave' => 'Alimentos',
    'sheep' => 'Alimentos', 'oveja' => 'Alimentos', 'lamb' => 'Alimentos',
    'cordero' => 'Alimentos', 'goat' => 'Alimentos', 'cabra' => 'Alimentos',
    
    // Lácteos
    'dairy' => 'Alimentos', 'lechería' => 'Alimentos', 'leche' => 'Alimentos',
    'milk' => 'Alimentos', 'cheese' => 'Alimentos', 'queso' => 'Alimentos',
    'yogurt' => 'Alimentos', 'butter' => 'Alimentos', 'mantequilla' => 'Alimentos',
    
    // Huevos
    'huevo' => 'Alimentos', 'egg' => 'Alimentos', 'eggs' => 'Alimentos',
    
    // Pesca
    'pesca' => 'Alimentos', 'fishing' => 'Alimentos', 'pesquero' => 'Alimentos',
    'fishery' => 'Alimentos', 'fish' => 'Alimentos', 'seafood' => 'Alimentos',
    'mariscos' => 'Alimentos', 'acuicultura' => 'Alimentos', 'aquaculture' => 'Alimentos',
    'salmon' => 'Alimentos', 'salmón' => 'Alimentos', 'tuna' => 'Alimentos',
    'atún' => 'Alimentos', 'shrimp' => 'Alimentos', 'camarón' => 'Alimentos',
    
    // Vegetales y frutas
    'vegetal' => 'Alimentos', 'vegetable' => 'Alimentos', 'fruta' => 'Alimentos',
    'fruit' => 'Alimentos', 'tomate' => 'Alimentos', 'tomato' => 'Alimentos',
    'papa' => 'Alimentos', 'potato' => 'Alimentos', 'maíz' => 'Alimentos',
    'corn' => 'Alimentos', 'trigo' => 'Alimentos', 'wheat' => 'Alimentos',
    'arroz' => 'Alimentos', 'rice' => 'Alimentos', 'soya' => 'Alimentos',
    'soy' => 'Alimentos', 'soja' => 'Alimentos', 'bean' => 'Alimentos',
    'frijol' => 'Alimentos', 'legume' => 'Alimentos', 'legumbre' => 'Alimentos',
    
    // Alimentos procesados
    'alimento' => 'Alimentos', 'food' => 'Alimentos', 'comida' => 'Alimentos',
    'meal' => 'Alimentos', 'bread' => 'Alimentos', 'pan' => 'Alimentos',
    'pasta' => 'Alimentos', 'cereal' => 'Alimentos', 'flour' => 'Alimentos',
    'harina' => 'Alimentos', 'sugar' => 'Alimentos', 'azúcar' => 'Alimentos',
    'salt' => 'Alimentos', 'sal' => 'Alimentos', 'oil' => 'Alimentos',
    'aceite' => 'Alimentos', 'wine' => 'Alimentos', 'vino' => 'Alimentos',
    'beer' => 'Alimentos', 'cerveza' => 'Alimentos', 'beverage' => 'Alimentos',
    'bebida' => 'Alimentos', 'juice' => 'Alimentos', 'jugo' => 'Alimentos',
    
    // Silvicultura (puede ser alimentos o construcción, pero lo ponemos en alimentos por frutos)
    'forestry' => 'Alimentos', 'silvicultura' => 'Alimentos', 'forestal' => 'Alimentos',
    'forest' => 'Alimentos',
    
    // Restaurantes/servicio
    'restaurante' => 'Alimentos', 'restaurant' => 'Alimentos', 'catering' => 'Alimentos',
    'food service' => 'Alimentos', 'servicio de comidas' => 'Alimentos',
    
    // ==================== AGUA ====================
    'agua' => 'Agua', 'water' => 'Agua', 'hídrico' => 'Agua', 'hydric' => 'Agua',
    'potable' => 'Agua', 'drinking water' => 'Agua', 'agua potable' => 'Agua',
    
    // Suministro
    'suministro de agua' => 'Agua', 'water supply' => 'Agua', 'distribución de agua' => 'Agua',
    'water distribution' => 'Agua', 'captación' => 'Agua', 'catchment' => 'Agua',
    'bombeo' => 'Agua', 'pumping' => 'Agua', 'pump' => 'Agua', 'bomba' => 'Agua',
    
    // Tratamiento
    'tratamiento de agua' => 'Agua', 'water treatment' => 'Agua', 'purificación' => 'Agua',
    'purification' => 'Agua', 'potabilización' => 'Agua', 'potabilization' => 'Agua',
    'filtración' => 'Agua', 'filtration' => 'Agua', 'filter' => 'Agua', 'filtro' => 'Agua',
    
    // Desalinización
    'desalinización' => 'Agua', 'desalination' => 'Agua', 'desalinate' => 'Agua',
    
    // Aguas residuales
    'aguas residuales' => 'Agua', 'wastewater' => 'Agua', 'sewage' => 'Agua',
    'alcantarillado' => 'Agua', 'sewerage' => 'Agua', 'saneamiento' => 'Agua',
    'sanitation' => 'Agua', 'depuración' => 'Agua', 'depuration' => 'Agua',
    
    // Plantas de tratamiento
    'WWTP' => 'Agua', 'PTAR' => 'Agua', 'wastewater treatment plant' => 'Agua',
    'planta de tratamiento' => 'Agua', 'treatment plant' => 'Agua',
];

// ===== 21 CLASIFICACIONES ISIC (YA EXPANDIDAS) =====
$sectores_isic = [
    'A' => [
        'nombre' => 'agriculture, forestry and fishing (A)',
        'sector_principal' => 'Alimentos',
        'palabras_clave' => [
            'agricultura', 'agriculture', 'agrícola', 'agricultural', 'agro', 'farm', 'farming',
            'granja', 'ranch', 'cultivo', 'crop', 'cultivation', 'harvest', 'cosecha', 'siembra',
            'planting', 'sowing', 'seed', 'semilla', 'fertilizer', 'fertilizante', 'pesticide',
            'pesticida', 'herbicide', 'insecticide', 'organic farming', 'agricultura orgánica',
            'silvicultura', 'forestry', 'forestal', 'forest', 'bosque', 'madera', 'wood', 'timber',
            'lumber', 'logging', 'tala', 'reforestación', 'reforestation', 'tree', 'árbol',
            'pesca', 'fishing', 'pesquero', 'fishery', 'fish', 'seafood', 'mariscos', 'acuicultura',
            'aquaculture', 'piscicultura', 'fish farming', 'marine', 'marino',
            'ganadería', 'livestock', 'cattle', 'ganado', 'vacuno', 'bovino', 'bovine', 'beef',
            'res', 'vaca', 'cow', 'cerdo', 'pig', 'pork', 'porcino', 'swine', 'pollo', 'chicken',
            'poultry', 'avicultura', 'ave', 'sheep', 'oveja', 'lamb', 'goat', 'cabra', 'dairy',
            'lechería', 'leche', 'milk', 'cheese', 'queso', 'huevo', 'egg'
        ],
        'peso' => 10
    ],
    'B' => [
        'nombre' => 'mining and quarrying (B)',
        'sector_principal' => 'Construccion',
        'palabras_clave' => [
            'minas', 'mining', 'mina', 'mine', 'minería', 'minero', 'miner', 'extracción',
            'extraction', 'excavación', 'excavation', 'canteras', 'quarrying', 'cantera', 'quarry',
            'mineral', 'minerals', 'ore', 'metal', 'metálico', 'metallic', 'coal', 'carbón',
            'copper', 'cobre', 'gold', 'oro', 'silver', 'plata', 'iron', 'hierro', 'zinc',
            'aluminio', 'aluminum', 'aluminium', 'bauxite', 'bauxita', 'nickel', 'níquel',
            'tin', 'estaño', 'lead', 'plomo', 'uranium', 'uranio', 'lithium', 'litio',
            'gravel', 'grava', 'sand', 'arena', 'stone', 'piedra', 'marble', 'mármol',
            'granite', 'granito', 'limestone', 'caliza', 'aggregate', 'áridos'
        ],
        'peso' => 10
    ],
    'C' => [
        'nombre' => 'manufacturing (C)',
        'sector_principal' => null,
        'palabras_clave' => [
            'manufactura', 'manufacturing', 'manufacture', 'producción', 'production', 'produce',
            'fábrica', 'factory', 'planta', 'plant', 'industrial', 'industry', 'industria',
            'proceso', 'process', 'processing', 'fabricación', 'fabrication', 'ensamblaje',
            'assembly', 'assembling', 'químico', 'chemical', 'química', 'chemistry', 'petroquímico',
            'petrochemical', 'refinería', 'refinery', 'refining', 'síntesis', 'synthesis',
            'explosiv', 'explosive', 'explosivo', 'dynamite', 'dinamita', 'detonation',
            'plástico', 'plastic', 'polymer', 'polímero', 'polyethylene', 'polietileno',
            'textil', 'textile', 'fabric', 'tela', 'cloth', 'cotton', 'algodón',
            'acero', 'steel', 'foundry', 'fundición', 'casting', 'forging',
            'electronic', 'electrónico', 'electronics', 'semiconductor', 'chip',
            'papel', 'paper', 'pulp', 'pulpa', 'cardboard', 'cartón',
            'vidrio', 'glass', 'cristal', 'maquinaria', 'machinery', 'máquina', 'machine'
        ],
        'peso' => 8
    ],
    'D' => [
        'nombre' => 'electricity, gas, steam & AC supply (D)',
        'sector_principal' => 'Energia',
        'palabras_clave' => [
            'electricidad', 'electricity', 'eléctrico', 'electric', 'electrical', 'power',
            'potencia', 'energía eléctrica', 'electric energy', 'suministro eléctrico',
            'electric supply', 'power supply', 'distribución eléctrica', 'power distribution',
            'generación', 'generation', 'generating', 'generador', 'generator', 'central',
            'power plant', 'power station', 'planta eléctrica',
            'kWh', 'MWh', 'GWh', 'kilowatt', 'megawatt', 'gigawatt', 'watt', 'volt',
            'transformador', 'transformer', 'turbina', 'turbine', 'alternador', 'alternator',
            'gas natural', 'natural gas', 'gas supply', 'suministro de gas',
            'vapor', 'steam', 'boiler', 'caldera', 'heating', 'calefacción'
        ],
        'peso' => 10
    ],
    'E' => [
        'nombre' => 'water supply; sewerage, waste management & remediation (E)',
        'sector_principal' => null,
        'palabras_clave' => [
            'agua', 'water', 'hídrico', 'hydric', 'potable', 'drinking water', 'agua potable',
            'suministro de agua', 'water supply', 'tratamiento de agua', 'water treatment',
            'purificación', 'purification', 'potabilización', 'desalination', 'desalinización',
            'aguas residuales', 'wastewater', 'sewage', 'alcantarillado', 'sewerage',
            'saneamiento', 'sanitation', 'depuración', 'WWTP', 'PTAR',
            'residuos', 'waste', 'residuo', 'basura', 'trash', 'garbage',
            'reciclaje', 'recycling', 'recycle', 'RSU', 'MSW', 'municipal solid waste',
            'vertedero', 'landfill', 'incineración', 'incineration', 'compost', 'compostaje',
            'remediación', 'remediation', 'limpieza', 'cleanup'
        ],
        'peso' => 10
    ],
    'F' => [
        'nombre' => 'construction (F)',
        'sector_principal' => 'Construccion',
        'palabras_clave' => [
            'construcción', 'construction', 'building', 'edificación', 'obra', 'estructura',
            'structure', 'infraestructura', 'infrastructure', 'cemento', 'cement', 'concreto',
            'concrete', 'hormigón', 'ladrillo', 'brick', 'edificio', 'pavimento', 'pavement',
            'asfalto', 'asphalt', 'puente', 'bridge', 'carretera', 'road', 'highway'
        ],
        'peso' => 10
    ],
    'G' => [
        'nombre' => 'wholesale/retail trade; motor vehicle repair (G)',
        'sector_principal' => null,
        'palabras_clave' => [
            'comercio', 'trade', 'retail', 'mayorista', 'wholesale', 'venta', 'sales',
            'distribuidor', 'distributor', 'tienda', 'store', 'shop'
        ],
        'peso' => 5
    ],
    'H' => [
        'nombre' => 'transportation and storage (H)',
        'sector_principal' => 'Energia',
        'palabras_clave' => [
            'transporte', 'transport', 'transportation', 'almacenamiento', 'storage',
            'logística', 'logistics', 'freight', 'carga', 'camión', 'truck', 'vehículo',
            'vehicle', 'barco', 'ship', 'avión', 'aircraft', 'tren', 'train', 'tkm', 'pkm'
        ],
        'peso' => 8
    ],
    'I' => [
        'nombre' => 'accommodation and food service (I)',
        'sector_principal' => 'Alimentos',
        'palabras_clave' => [
            'alojamiento', 'accommodation', 'hotel', 'restaurante', 'restaurant',
            'servicio de comidas', 'food service', 'catering', 'hospedaje'
        ],
        'peso' => 8
    ],
    'J' => [
        'nombre' => 'information and communication (J)',
        'sector_principal' => 'Energia',
        'palabras_clave' => [
            'información', 'information', 'comunicación', 'communication', 'telecomunicaciones',
            'telecommunications', 'software', 'tecnología', 'technology', 'internet', 'data'
        ],
        'peso' => 5
    ],
    'K' => ['nombre' => 'financial and insurance (K)', 'sector_principal' => null, 'palabras_clave' => ['financiero', 'financial', 'seguros', 'insurance', 'banco', 'bank'], 'peso' => 5],
    'L' => ['nombre' => 'real estate (L)', 'sector_principal' => 'Construccion', 'palabras_clave' => ['inmobiliaria', 'real estate', 'propiedad', 'property'], 'peso' => 6],
    'M' => ['nombre' => 'professional, scientific & technical (M)', 'sector_principal' => null, 'palabras_clave' => ['profesional', 'professional', 'científico', 'scientific', 'técnico', 'technical'], 'peso' => 5],
    'N' => ['nombre' => 'administrative & support (N)', 'sector_principal' => null, 'palabras_clave' => ['administrativo', 'administrative', 'apoyo', 'support'], 'peso' => 4],
    'O' => ['nombre' => 'public administration & defence (O)', 'sector_principal' => null, 'palabras_clave' => ['administración pública', 'gobierno', 'government', 'defensa'], 'peso' => 5],
    'P' => ['nombre' => 'education (P)', 'sector_principal' => null, 'palabras_clave' => ['educación', 'education', 'escuela', 'school', 'universidad'], 'peso' => 5],
    'Q' => ['nombre' => 'human health & social work (Q)', 'sector_principal' => null, 'palabras_clave' => ['salud', 'health', 'hospital', 'médico', 'medical'], 'peso' => 5],
    'R' => ['nombre' => 'arts, entertainment & recreation (R)', 'sector_principal' => null, 'palabras_clave' => ['arte', 'arts', 'entretenimiento', 'entertainment', 'recreación'], 'peso' => 4],
    'S' => ['nombre' => 'other services (S)', 'sector_principal' => null, 'palabras_clave' => ['otros servicios', 'other services', 'servicio'], 'peso' => 3],
    'T' => ['nombre' => 'households as employers (T)', 'sector_principal' => null, 'palabras_clave' => ['hogares', 'households', 'empleador doméstico'], 'peso' => 4],
    'U' => ['nombre' => 'extra-territorial organizations (U)', 'sector_principal' => null, 'palabras_clave' => ['organización extraterritorial', 'embajada', 'embassy'], 'peso' => 4]
];

// ===== FUNCIÓN DE CLASIFICACIÓN CON LÓGICA MEJORADA =====
function clasificarProceso($texto, $sectores_isic, $mapeo_directo, $mapeo_isic_principal) {
    $texto_lower = mb_strtolower($texto, 'UTF-8');
    $puntuaciones_isic = [];
    $puntuaciones_principal = [];
    
    // PASO 1: Clasificar ISIC
    foreach ($sectores_isic as $letra => $config) {
        $puntuacion = 0;
        $palabras_encontradas = [];
        
        foreach ($config['palabras_clave'] as $palabra) {
            $palabra_lower = mb_strtolower($palabra, 'UTF-8');
            
            if (preg_match('/\b' . preg_quote($palabra_lower, '/') . '\b/u', $texto_lower)) {
                $puntuacion += ($config['peso'] * 3);
                $palabras_encontradas[] = $palabra;
            }
            elseif (strpos($texto_lower, $palabra_lower) !== false) {
                $puntuacion += ($config['peso'] * 1.5);
                $palabras_encontradas[] = $palabra . '*';
            }
            else {
                if (strlen($palabra_lower) >= 5) {
                    $stem = substr($palabra_lower, 0, 5);
                    if (strpos($texto_lower, $stem) !== false) {
                        $puntuacion += $config['peso'];
                        $palabras_encontradas[] = $palabra . '~';
                    }
                }
            }
        }
        
        if ($puntuacion > 0) {
            $puntuaciones_isic[$letra] = [
                'puntos' => $puntuacion,
                'palabras' => $palabras_encontradas,
                'nombre' => $config['nombre'],
                'sector_principal_sugerido' => $config['sector_principal']
            ];
        }
    }
    
    // PASO 2: Determinar sector principal CON BÚSQUEDA ULTRA AGRESIVA
    foreach ($mapeo_directo as $palabra_clave => $sector) {
        $palabra_lower = mb_strtolower($palabra_clave, 'UTF-8');
        
        // Exacta
        if (preg_match('/\b' . preg_quote($palabra_lower, '/') . '\b/u', $texto_lower)) {
            if (!isset($puntuaciones_principal[$sector])) {
                $puntuaciones_principal[$sector] = 0;
            }
            $puntuaciones_principal[$sector] += 20;
        }
        // Parcial
        elseif (strpos($texto_lower, $palabra_lower) !== false) {
            if (!isset($puntuaciones_principal[$sector])) {
                $puntuaciones_principal[$sector] = 0;
            }
            $puntuaciones_principal[$sector] += 10;
        }
        // Stem para palabras largas
        elseif (strlen($palabra_lower) >= 5) {
            $stem = substr($palabra_lower, 0, 5);
            if (strpos($texto_lower, $stem) !== false) {
                if (!isset($puntuaciones_principal[$sector])) {
                    $puntuaciones_principal[$sector] = 0;
                }
                $puntuaciones_principal[$sector] += 5;
            }
        }
    }
    
    // PASO 3: Si no hay sector principal pero sí ISIC, inferir desde ISIC
    if (empty($puntuaciones_principal) && !empty($puntuaciones_isic)) {
        $isic_ganador = array_key_first($puntuaciones_isic);
        if (isset($mapeo_isic_principal[$isic_ganador]) && $mapeo_isic_principal[$isic_ganador] !== null) {
            $puntuaciones_principal[$mapeo_isic_principal[$isic_ganador]] = 10;
        }
        // Si el ISIC tiene sector sugerido, usarlo
        elseif ($sectores_isic[$isic_ganador]['sector_principal'] !== null) {
            $puntuaciones_principal[$sectores_isic[$isic_ganador]['sector_principal']] = 10;
        }
    }
    
    // PASO 4: Para ISIC E (agua/residuos), decidir basándose en palabras específicas
    if (!empty($puntuaciones_isic) && array_key_first($puntuaciones_isic) === 'E') {
        // Si menciona agua más que residuos, asignar Agua
        $menciones_agua = 0;
        $menciones_residuos = 0;
        
        $palabras_agua = ['agua', 'water', 'potable', 'wastewater', 'aguas residuales', 'alcantarillado', 'sewage', 'tratamiento de agua', 'purificación'];
        $palabras_residuos = ['residuos', 'waste', 'basura', 'trash', 'reciclaje', 'recycling', 'vertedero', 'landfill', 'incineración'];
        
        foreach ($palabras_agua as $p) {
            if (strpos($texto_lower, $p) !== false) $menciones_agua++;
        }
        foreach ($palabras_residuos as $p) {
            if (strpos($texto_lower, $p) !== false) $menciones_residuos++;
        }
        
        if ($menciones_agua > $menciones_residuos) {
            $puntuaciones_principal['Agua'] = 15;
        } elseif ($menciones_residuos > $menciones_agua) {
            $puntuaciones_principal['Residuos'] = 15;
        } else {
            // Empate o ninguno, usar el que tenga más puntos
            if (isset($puntuaciones_principal['Agua']) || isset($puntuaciones_principal['Residuos'])) {
                // Ya hay uno asignado, mantenerlo
            } else {
                // Asignar Agua por defecto para E
                $puntuaciones_principal['Agua'] = 8;
            }
        }
    }
    
    uasort($puntuaciones_isic, function($a, $b) {
        return $b['puntos'] - $a['puntos'];
    });
    
    arsort($puntuaciones_principal);
    
    return [
        'isic' => $puntuaciones_isic,
        'principal' => $puntuaciones_principal
    ];
}

// ===== RESTO DEL CÓDIGO (HTML, BD, CLASIFICACIÓN) - IGUAL QUE ANTES =====
echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Clasificador Ultra-Expandido LCA v4.0</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1600px; margin: 0 auto; background: white; border-radius: 20px; box-shadow: 0 30px 90px rgba(0,0,0,0.4); overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 50px 40px; text-align: center; }
        .header h1 { font-size: 2.8rem; margin-bottom: 15px; }
        .header p { font-size: 1.2rem; opacity: 0.95; }
        .content { padding: 40px; }
        .status-box { padding: 25px; border-radius: 15px; margin-bottom: 25px; display: flex; align-items: center; gap: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .status-box.success { background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left: 6px solid #28a745; color: #155724; }
        .status-box.error { background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-left: 6px solid #dc3545; color: #721c24; }
        .status-box.info { background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); border-left: 6px solid #17a2b8; color: #0c5460; }
        .icon { font-size: 2.5rem; }
        .proceso { padding: 20px; margin: 15px 0; border-radius: 12px; border-left: 5px solid #667eea; background: #f8f9fa; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .proceso:hover { transform: translateX(8px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
        .proceso.clasificado { border-left-color: #28a745; background: linear-gradient(135deg, #d4edda 0%, #e8f5e9 100%); }
        .proceso.no-clasificado { border-left-color: #ffc107; background: linear-gradient(135deg, #fff3cd 0%, #fff9e6 100%); }
        .proceso-nombre { font-size: 1.3rem; font-weight: 600; color: #2c3e50; margin-bottom: 15px; }
        .clasificacion-dual { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
        .clasificacion-box { background: white; padding: 15px; border-radius: 8px; border: 2px solid #e9ecef; }
        .clasificacion-box h4 { color: #495057; font-size: 0.9rem; margin-bottom: 8px; text-transform: uppercase; }
        .clasificacion-box .valor { font-size: 1.1rem; font-weight: 600; color: #28a745; }
        .confidence { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 0.85rem; font-weight: bold; margin-left: 10px; }
        .confidence.high { background: #28a745; color: white; }
        .confidence.medium { background: #ffc107; color: #333; }
        .confidence.low { background: #dc3545; color: white; }
        .palabras-clave { color: #666; font-size: 0.9rem; margin-top: 10px; }
        .resumen { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 15px; margin: 40px 0; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 25px; }
        .stat-box { background: rgba(255,255,255,0.2); padding: 25px; border-radius: 12px; text-align: center; }
        .stat-box .number { font-size: 3.5rem; font-weight: bold; display: block; }
        .stat-box .label { font-size: 1.1rem; opacity: 0.95; margin-top: 8px; }
        .progress-bar { background: rgba(255,255,255,0.3); border-radius: 15px; overflow: hidden; height: 40px; margin: 25px 0; }
        .progress-fill { background: linear-gradient(90deg, #28a745, #20c997); height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.1rem; transition: width 0.5s ease; }
    </style>
</head>
<body>
<div class='container'>
    <div class='header'>
        <h1>🚀 Clasificador Ultra-Expandido v4.0</h1>
        <p>+800 Palabras Clave | Lógica de Inferencia ISIC→Principal | Stems | Modo Agresivo</p>
    </div>
    <div class='content'>";

try {
    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='status-box success'><span class='icon'>✅</span><div><strong>Conexión exitosa</strong></div></div>";
} catch (PDOException $e) {
    echo "<div class='status-box error'><span class='icon'>❌</span><div>" . htmlspecialchars($e->getMessage()) . "</div></div>";
    die("</div></div></body></html>");
}

try {
    $check1 = $pdo->query("SHOW COLUMNS FROM processes LIKE 'sector_principal'");
    if ($check1->rowCount() == 0) { $pdo->exec("ALTER TABLE processes ADD COLUMN sector_principal VARCHAR(50) DEFAULT NULL"); }
    $check2 = $pdo->query("SHOW COLUMNS FROM processes LIKE 'norma_isic'");
    if ($check2->rowCount() == 0) { $pdo->exec("ALTER TABLE processes ADD COLUMN norma_isic VARCHAR(60) DEFAULT NULL"); }
    echo "<div class='status-box success'><span class='icon'>✅</span><div><strong>Columnas verificadas</strong></div></div>";
} catch (PDOException $e) {
    echo "<div class='status-box error'><span class='icon'>❌</span><div>" . htmlspecialchars($e->getMessage()) . "</div></div>";
    die("</div></div></body></html>");
}

try {
    $count = $pdo->query("SELECT COUNT(*) as total FROM processes")->fetch()['total'];
    $stmt = $pdo->query("SELECT uuid, name, description, category FROM processes");
    $procesos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<div class='status-box info'><span class='icon'>📊</span><div><strong>Total: {$count}</strong></div></div>";
} catch (PDOException $e) {
    echo "<div class='status-box error'><span class='icon'>❌</span><div>" . htmlspecialchars($e->getMessage()) . "</div></div>";
    die("</div></div></body></html>");
}

$actualizados = 0;
$no_clasificados = 0;
$contador = 0;
$confianza_alta = 0;
$confianza_media = 0;
$confianza_baja = 0;

echo "<h2 style='margin: 40px 0 25px 0; font-size: 2rem;'>📋 Clasificación Ultra-Agresiva</h2>";

foreach ($procesos as $proceso) {
    $contador++;
    $texto_completo = implode(' | ', [$proceso['name'] ?? '', $proceso['description'] ?? '', $proceso['category'] ?? '']);
    $resultado = clasificarProceso($texto_completo, $sectores_isic, $mapeo_directo_sector_principal, $mapeo_isic_a_principal);
    
    $isic_clasificado = !empty($resultado['isic']) ? array_key_first($resultado['isic']) : null;
    $principal_clasificado = !empty($resultado['principal']) ? array_key_first($resultado['principal']) : null;
    
    if ($isic_clasificado || $principal_clasificado) {
        $norma_isic_valor = $isic_clasificado ? $sectores_isic[$isic_clasificado]['nombre'] : null;
        $sector_principal_valor = $principal_clasificado;
        
        $puntuacion_isic = $isic_clasificado ? $resultado['isic'][$isic_clasificado]['puntos'] : 0;
        if ($puntuacion_isic >= 30) { $confianza = 'ALTA'; $confianza_class = 'high'; $confianza_alta++; }
        elseif ($puntuacion_isic >= 15) { $confianza = 'MEDIA'; $confianza_class = 'medium'; $confianza_media++; }
        else { $confianza = 'BAJA'; $confianza_class = 'low'; $confianza_baja++; }
        
        try {
            $upd = $pdo->prepare("UPDATE processes SET norma_isic = :norma_isic, sector_principal = :sector_principal WHERE uuid = :uuid");
            $upd->execute([':norma_isic' => $norma_isic_valor, ':sector_principal' => $sector_principal_valor, ':uuid' => $proceso['uuid']]);
            $actualizados++;
            
            echo "<div class='proceso clasificado'>";
            echo "<div class='proceso-nombre'>#{$contador} - " . htmlspecialchars($proceso['name']) . "<span class='confidence {$confianza_class}'>{$confianza}</span></div>";
            echo "<div class='clasificacion-dual'>";
            echo "<div class='clasificacion-box'><h4>🏭 Sector Principal</h4><div class='valor'>" . ($sector_principal_valor ?? 'N/A') . "</div></div>";
            echo "<div class='clasificacion-box'><h4>🌍 Norma ISIC</h4><div class='valor'>" . ($norma_isic_valor ?? 'N/A') . "</div></div>";
            echo "</div>";
            if (!empty($resultado['isic'][$isic_clasificado]['palabras'])) {
                echo "<div class='palabras-clave'><strong>Palabras:</strong> " . htmlspecialchars(implode(', ', array_slice($resultado['isic'][$isic_clasificado]['palabras'], 0, 6))) . "</div>";
            }
            echo "</div>";
        } catch (PDOException $e) {
            echo "<div class='status-box error'><span class='icon'>❌</span><div>" . htmlspecialchars($e->getMessage()) . "</div></div>";
        }
    } else {
        $no_clasificados++;
        echo "<div class='proceso no-clasificado'>";
        echo "<div class='proceso-nombre'>#{$contador} - " . htmlspecialchars($proceso['name']) . "</div>";
        echo "<div style='color: #856404;'>⚠️ Sin clasificar - Revisión manual</div>";
        echo "<div style='font-size: 0.9rem; color: #666; margin-top: 5px;'><em>" . htmlspecialchars(substr($texto_completo, 0, 120)) . "...</em></div>";
        echo "</div>";
    }
}

$porcentaje = $count > 0 ? round(($actualizados / $count) * 100, 1) : 0;

echo "<div class='resumen'>";
echo "<h2>📊 Resumen</h2>";
echo "<div class='progress-bar'><div class='progress-fill' style='width: {$porcentaje}%'>{$porcentaje}%</div></div>";
echo "<div class='stats'>";
echo "<div class='stat-box'><span class='number'>{$count}</span><span class='label'>Total</span></div>";
echo "<div class='stat-box'><span class='number'>{$actualizados}</span><span class='label'>✅ Clasificados</span></div>";
echo "<div class='stat-box'><span class='number'>{$no_clasificados}</span><span class='label'>⚠️ Sin Clasificar</span></div>";
echo "<div class='stat-box'><span class='number'>{$confianza_alta}</span><span class='label'>🎯 Alta</span></div>";
echo "<div class='stat-box'><span class='number'>{$confianza_media}</span><span class='label'>📊 Media</span></div>";
echo "<div class='stat-box'><span class='number'>{$confianza_baja}</span><span class='label'>⚡ Baja</span></div>";
echo "</div>";

echo "<div style='margin-top: 30px; padding: 20px; background: rgba(255,255,255,0.2); border-radius: 10px;'>";
echo "<h3>✨ Nuevas Mejoras v4.0</h3>";
echo "<ul style='margin-top: 15px; line-height: 2;'>";
echo "<li>✅ +800 palabras clave (duplicado respecto a v3)</li>";
echo "<li>✅ Mapeo directo ISIC → Sector Principal (inferencia automática)</li>";
echo "<li>✅ Lógica especial para ISIC E (agua vs residuos)</li>";
echo "<li>✅ Búsqueda por stems más agresiva</li>";
echo "<li>✅ Puntuación mejorada (x20 exacta, x10 parcial, x5 stem)</li>";
echo "</ul>";
echo "</div>";

echo "</div></div></div></body></html>";
?>

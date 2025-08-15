-- INSERTS PARA catalogo_sat_tipo_comprobante
INSERT INTO
    catalogo_sat_tipo_comprobante (
        clave,
        descripcion,
        valor_maximo,
        fecha_inicio,
        fecha_fin
    )
VALUES
    (
        'I',
        'Ingreso',
        '999999999999999999.999999',
        '2022-01-01',
        NULL
    ),
    (
        'E',
        'Egreso',
        '999999999999999999.999999',
        '2022-01-01',
        NULL
    ),
    ('T', 'Traslado', '0', '2022-01-01', NULL),
    ('N', 'Nómina', NULL, '2022-01-01', NULL),
    (
        'P',
        'Pago',
        '999999999999999999.999999',
        '2022-01-01',
        NULL
    );

-- INSERTS PARA catalogo_sat_forma_pago
INSERT INTO
    catalogo_sat_forma_pago (
        clave,
        descripcion,
        bancarizado,
        numero_operacion,
        rfc_emisor_cuenta_ordenante,
        cuenta_ordenante,
        patron_cuenta_ordenante,
        rfc_emisor_cuenta_beneficiario,
        cuenta_beneficiario,
        patron_cuenta_beneficiario,
        tipo_cadena_pago,
        nombre_banco_extranjero,
        fecha_inicio,
        fecha_fin
    )
VALUES
    (
        '01',
        'Efectivo',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '02',
        'Cheque nominativo',
        'Sí',
        'Opcional',
        'Opcional',
        'Opcional',
        '[0-9]{11}|[0-9]{18}',
        'Opcional',
        'Opcional',
        '[0-9]{10,11}|[0-9]{15,16}|[0-9]{18}|[A-Z0-9_]{10,50}',
        'No',
        'Si el RFC del emisor de la cuenta ordenante es XEXX010101000, este campo es obligatorio.',
        '2022-01-01',
        NULL
    ),
    (
        '03',
        'Transferencia electrónica de fondos',
        'Sí',
        'Opcional',
        'Opcional',
        'Opcional',
        '[0-9]{10}|[0-9]{16}|[0-9]{18}',
        'Opcional',
        'Opcional',
        '[0-9]{10}|[0-9]{18}',
        'Opcional',
        'Si el RFC del emisor de la cuenta ordenante es XEXX010101000, este campo es obligatorio.',
        '2022-01-01',
        NULL
    ),
    (
        '04',
        'Tarjeta de crédito',
        'Sí',
        'Opcional',
        'Opcional',
        'Opcional',
        '[0-9]{16}',
        'Opcional',
        'Opcional',
        '[0-9]{10,11}|[0-9]{15,16}|[0-9]{18}|[A-Z0-9_]{10,50}',
        'No',
        'Si el RFC del emisor de la cuenta ordenante es XEXX010101000, este campo es obligatorio.',
        '2022-01-01',
        NULL
    ),
    (
        '05',
        'Monedero electrónico',
        'Sí',
        'Opcional',
        'Opcional',
        'Opcional',
        '[0-9]{10,11}|[0-9]{15,16}|[0-9]{18}|[A-Z0-9_]{10,50}',
        'Opcional',
        'Opcional',
        '[0-9]{10,11}|[0-9]{15,16}|[0-9]{18}|[A-Z0-9_]{10,50}',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '06',
        'Dinero electrónico',
        'Sí',
        'Opcional',
        'Opcional',
        'Opcional',
        '[0-9]{10}',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '08',
        'Vales de despensa',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '12',
        'Dación en pago',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '13',
        'Pago por subrogación',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '14',
        'Pago por consignación',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '15',
        'Condonación',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '17',
        'Compensación',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '23',
        'Novación',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '24',
        'Confusión',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '25',
        'Remisión de deuda',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '26',
        'Prescripción o caducidad',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '27',
        'A satisfacción del acreedor',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '28',
        'Tarjeta de débito',
        'Sí',
        'Opcional',
        'Opcional',
        'Opcional',
        '[0-9]{16}',
        'Opcional',
        'Opcional',
        '[0-9]{10,11}|[0-9]{15,16}|[0-9]{18}|[A-Z0-9_]{10,50}',
        'No',
        'Si el RFC del emisor de la cuenta ordenante es XEXX010101000, este campo es obligatorio.',
        '2022-01-01',
        NULL
    ),
    (
        '29',
        'Tarjeta de servicios',
        'Sí',
        'Opcional',
        'Opcional',
        'Opcional',
        '[0-9]{15,16}',
        'Opcional',
        'Opcional',
        '[0-9]{10,11}|[0-9]{15,16}|[0-9]{18}|[A-Z0-9_]{10,50}',
        'No',
        'Si el RFC del emisor de la cuenta ordenante es XEXX010101000, este campo es obligatorio.',
        '2022-01-01',
        NULL
    ),
    (
        '30',
        'Aplicación de anticipos',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '31',
        'Intermediario pagos',
        'No',
        'Opcional',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '99',
        'Por definir',
        'Opcional',
        'Opcional',
        'Opcional',
        'Opcional',
        'Opcional',
        'Opcional',
        'Opcional',
        'Opcional',
        'Opcional',
        'Opcional',
        '2022-01-01',
        NULL
    );

-- INSERTS PARA catalogo_sat_metodo_pago
INSERT INTO
    catalogo_sat_metodo_pago (clave, descripcion, fecha_inicio, fecha_fin)
VALUES
    (
        'PUE',
        'Pago en una sola exhibición',
        '2022-01-01',
        NULL
    ),
    (
        'PPD',
        'Pago en parcialidades o diferido',
        '2022-01-01',
        NULL
    );

-- INSERTS PARA catalogo_sat_moneda
INSERT INTO
    catalogo_sat_moneda (
        clave,
        descripcion,
        decimales,
        porcentaje_variacion,
        fecha_inicio,
        fecha_fin
    )
VALUES
    (
        'AED',
        'Dirham de EAU',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('AFN', 'Afghani', 2, '500%', '2022-01-01', NULL),
    ('ALL', 'Lek', 2, '500%', '2022-01-01', NULL),
    (
        'AMD',
        'Dram armenio',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'ANG',
        'Florín antillano neerlandés',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('AOA', 'Kwanza', 2, '500%', '2022-01-01', NULL),
    (
        'ARS',
        'Peso Argentino',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'AUD',
        'Dólar Australiano',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'AWG',
        'Aruba Florin',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'AZN',
        'Azerbaijanian Manat',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'BAM',
        'Convertibles marca',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'BBD',
        'Dólar de Barbados',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('BDT', 'Taka', 2, '500%', '2022-01-01', NULL),
    (
        'BGN',
        'Lev búlgaro',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'BHD',
        'Dinar de Bahrein',
        3,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'BIF',
        'Burundi Franc',
        0,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'BMD',
        'Dólar de Bermudas',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'BND',
        'Dólar de Brunei',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('BOB', 'Boliviano', 2, '500%', '2022-01-01', NULL),
    ('BOV', 'Mvdol', 2, '500%', '2022-01-01', NULL),
    (
        'BRL',
        'Real brasileño',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'BSD',
        'Dólar de las Bahamas',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('BTN', 'Ngultrum', 2, '500%', '2022-01-01', NULL),
    ('BWP', 'Pula', 2, '500%', '2022-01-01', NULL),
    (
        'BYR',
        'Rublo bielorruso',
        0,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'BZD',
        'Dólar de Belice',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'CAD',
        'Dólar Canadiense',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'CDF',
        'Franco congoleño',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('CHE', 'WIR Euro', 2, '500%', '2022-01-01', NULL),
    (
        'CHF',
        'Franco Suizo',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('CHW', 'Franc WIR', 2, '500%', '2022-01-01', NULL),
    (
        'CLF',
        'Unidad de Fomento',
        4,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'CLP',
        'Peso chileno',
        0,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'CNY',
        'Yuan Renminbi',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'COP',
        'Peso Colombiano',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'COU',
        'Unidad de Valor real',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'CRC',
        'Colón costarricense',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'CUC',
        'Peso Convertible',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'CUP',
        'Peso Cubano',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'CVE',
        'Cabo Verde Escudo',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'CZK',
        'Corona checa',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'DJF',
        'Franco de Djibouti',
        0,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'DKK',
        'Corona danesa',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'DOP',
        'Peso Dominicano',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'DZD',
        'Dinar argelino',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'EGP',
        'Libra egipcia',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('ERN', 'Nakfa', 2, '500%', '2022-01-01', NULL),
    (
        'ETB',
        'Birr etíope',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('EUR', 'Euro', 2, '500%', '2022-01-01', NULL),
    (
        'FJD',
        'Dólar de Fiji',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'FKP',
        'Libra malvinense',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'GBP',
        'Libra Esterlina',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('GEL', 'Lari', 2, '500%', '2022-01-01', NULL),
    (
        'GHS',
        'Cedi de Ghana',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'GIP',
        'Libra de Gibraltar',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('GMD', 'Dalasi', 2, '500%', '2022-01-01', NULL),
    (
        'GNF',
        'Franco guineano',
        0,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('GTQ', 'Quetzal', 2, '500%', '2022-01-01', NULL),
    (
        'GYD',
        'Dólar guyanés',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'HKD',
        'Dólar De Hong Kong',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('HNL', 'Lempira', 2, '500%', '2022-01-01', NULL),
    ('HRK', 'Kuna', 2, '500%', '2022-01-01', NULL),
    ('HTG', 'Gourde', 2, '500%', '2022-01-01', NULL),
    ('HUF', 'Florín', 2, '500%', '2022-01-01', NULL),
    ('IDR', 'Rupia', 2, '500%', '2022-01-01', NULL),
    (
        'ILS',
        'Nuevo Shekel Israelí',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'INR',
        'Rupia india',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'IQD',
        'Dinar iraquí',
        3,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'IRR',
        'Rial iraní',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'ISK',
        'Corona islandesa',
        0,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'JMD',
        'Dólar Jamaiquino',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'JOD',
        'Dinar jordano',
        3,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('JPY', 'Yen', 0, '500%', '2022-01-01', NULL),
    (
        'KES',
        'Chelín keniano',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('KGS', 'Som', 2, '500%', '2022-01-01', NULL),
    ('KHR', 'Riel', 2, '500%', '2022-01-01', NULL),
    (
        'KMF',
        'Franco Comoro',
        0,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'KPW',
        'Corea del Norte ganó',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('KRW', 'Won', 0, '500%', '2022-01-01', NULL),
    (
        'KWD',
        'Dinar kuwaití',
        3,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'KYD',
        'Dólar de las Islas Caimán',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('KZT', 'Tenge', 2, '500%', '2022-01-01', NULL),
    ('LAK', 'Kip', 2, '500%', '2022-01-01', NULL),
    (
        'LBP',
        'Libra libanesa',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'LKR',
        'Rupia de Sri Lanka',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'LRD',
        'Dólar liberiano',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('LSL', 'Loti', 2, '500%', '2022-01-01', NULL),
    (
        'LYD',
        'Dinar libio',
        3,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'MAD',
        'Dirham marroquí',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'MDL',
        'Leu moldavo',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'MGA',
        'Ariary malgache',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('MKD', 'Denar', 2, '500%', '2022-01-01', NULL),
    ('MMK', 'Kyat', 2, '500%', '2022-01-01', NULL),
    ('MNT', 'Tugrik', 2, '500%', '2022-01-01', NULL),
    ('MOP', 'Pataca', 2, '500%', '2022-01-01', NULL),
    ('MRO', 'Ouguiya', 2, '500%', '2022-01-01', NULL),
    (
        'MUR',
        'Rupia de Mauricio',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('MVR', 'Rupia', 2, '500%', '2022-01-01', NULL),
    ('MWK', 'Kwacha', 2, '500%', '2022-01-01', NULL),
    (
        'MXN',
        'Peso Mexicano',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'MXV',
        'México Unidad de Inversión (UDI)',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'MYR',
        'Ringgit malayo',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'MZN',
        'Mozambique Metical',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'NAD',
        'Dólar de Namibia',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('NGN', 'Naira', 2, '500%', '2022-01-01', NULL),
    (
        'NIO',
        'Córdoba Oro',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'NOK',
        'Corona noruega',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'NPR',
        'Rupia nepalí',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'NZD',
        'Dólar de Nueva Zelanda',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'OMR',
        'Rial omaní',
        3,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('PAB', 'Balboa', 2, '500%', '2022-01-01', NULL),
    ('PEN', 'Nuevo Sol', 2, '500%', '2022-01-01', NULL),
    ('PGK', 'Kina', 2, '500%', '2022-01-01', NULL),
    (
        'PHP',
        'Peso filipino',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'PKR',
        'Rupia de Pakistán',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('PLN', 'Zloty', 2, '500%', '2022-01-01', NULL),
    ('PYG', 'Guaraní', 0, '500%', '2022-01-01', NULL),
    (
        'QAR',
        'Qatar Rial',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'RON',
        'Leu rumano',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'RSD',
        'Dinar serbio',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'RUB',
        'Rublo ruso',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'RWF',
        'Franco ruandés',
        0,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'SAR',
        'Riyal saudí',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'SBD',
        'Dólar de las Islas Salomón',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'SCR',
        'Rupia de Seychelles',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'SDG',
        'Libra sudanesa',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'SEK',
        'Corona sueca',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'SGD',
        'Dólar De Singapur',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'SHP',
        'Libra de Santa Helena',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('SLL', 'Leona', 2, '500%', '2022-01-01', NULL),
    (
        'SOS',
        'Chelín somalí',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'SRD',
        'Dólar de Suriname',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'SSP',
        'Libra sudanesa Sur',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('STD', 'Dobra', 2, '500%', '2022-01-01', NULL),
    (
        'SVC',
        'Colon El Salvador',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'SYP',
        'Libra Siria',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('SZL', 'Lilangeni', 2, '500%', '2022-01-01', NULL),
    ('THB', 'Baht', 2, '500%', '2022-01-01', NULL),
    ('TJS', 'Somoni', 2, '500%', '2022-01-01', NULL),
    (
        'TMT',
        'Turkmenistán nuevo manat',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'TND',
        'Dinar tunecino',
        3,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('TOP', 'Pa''anga', 2, '500%', '2022-01-01', NULL),
    (
        'TRY',
        'Lira turca',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'TTD',
        'Dólar de Trinidad y Tobago',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'TWD',
        'Nuevo dólar de Taiwán',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'TZS',
        'Shilling tanzano',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('UAH', 'Hryvnia', 2, '500%', '2022-01-01', NULL),
    (
        'UGX',
        'Shilling de Uganda',
        0,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'USD',
        'Dólar americano',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'USN',
        'Dólar estadounidense (día siguiente)',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'UYI',
        'Peso Uruguay en Unidades Indexadas (URUIURUI)',
        0,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'UYU',
        'Peso Uruguayo',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    (
        'UZS',
        'Uzbekistán Sum',
        2,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('VEF', 'Bolívar', 2, '500%', '2022-01-01', NULL),
    ('VND', 'Dong', 0, '500%', '2022-01-01', NULL),
    ('VUV', 'Vatu', 0, '500%', '2022-01-01', NULL),
    ('WST', 'Tala', 2, '500%', '2022-01-01', NULL),
    (
        'XAF',
        'Franco CFA BEAC',
        0,
        '500%',
        '2022-01-01',
        NULL
    ),
    ('XAG', 'Plata', 0, '500%', '2022-01-01', NULL),
    ('XAU', 'Oro', 0, '500%', '2022-01-01', NULL);

-- INSERTS PARA catalogo_sat_metodo_pago
INSERT INTO
    catalogo_sat_regimen_fiscal (
        clave,
        descripcion,
        fisica,
        moral,
        fecha_inicio,
        fecha_fin
    )
VALUES
    (
        '601',
        'General de Ley Personas Morales',
        'No',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        '603',
        'Personas Morales con Fines no Lucrativos',
        'No',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        '605',
        'Sueldos y Salarios e Ingresos Asimilados a Salarios',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '606',
        'Arrendamiento',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '607',
        'Régimen de Enajenación o Adquisición de Bienes',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '608',
        'Demás ingresos',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '610',
        'Residentes en el Extranjero sin Establecimiento Permanente en México',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        '611',
        'Ingresos por Dividendos (socios y accionistas)',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '612',
        'Personas Físicas con Actividades Empresariales y Profesionales',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '614',
        'Ingresos por intereses',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '615',
        'Régimen de los ingresos por obtención de premios',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '616',
        'Sin obligaciones fiscales',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '620',
        'Sociedades Cooperativas de Producción que optan por diferir sus ingresos',
        'No',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        '621',
        'Incorporación Fiscal',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '622',
        'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
        'No',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        '623',
        'Opcional para Grupos de Sociedades',
        'No',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        '624',
        'Coordinados',
        'No',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        '625',
        'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        '626',
        'Régimen Simplificado de Confianza',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    );

-- INSERTS PARA catalogo_sat_tasa_o_cuota
INSERT INTO
    catalogo_sat_tasa_o_cuota (
        tipo,
        valor_minimo,
        valor_maximo,
        impuesto,
        factor,
        traslado,
        retencion,
        fecha_inicio,
        fecha_fin
    )
VALUES
    (
        'Fijo',
        NULL,
        0.000000,
        'IVA',
        'Tasa',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.160000,
        'IVA',
        'Tasa',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        'Rango',
        0.000000,
        0.160000,
        'IVA',
        'Tasa',
        'No',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.080000,
        'IVA Crédito aplicado del 50%',
        'Tasa',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.265000,
        'IEPS',
        'Tasa',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.300000,
        'IEPS',
        'Tasa',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.530000,
        'IEPS',
        'Tasa',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.500000,
        'IEPS',
        'Tasa',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        1.600000,
        'IEPS',
        'Tasa',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.304000,
        'IEPS',
        'Tasa',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.250000,
        'IEPS',
        'Tasa',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.090000,
        'IEPS',
        'Tasa',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.080000,
        'IEPS',
        'Tasa',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.070000,
        'IEPS',
        'Tasa',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.060000,
        'IEPS',
        'Tasa',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.030000,
        'IEPS',
        'Tasa',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        'Fijo',
        NULL,
        0.000000,
        'IEPS',
        'Tasa',
        'Sí',
        'No',
        '2022-01-01',
        NULL
    ),
    (
        'Rango',
        0.000000,
        59.144900,
        'IEPS',
        'Cuota',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL
    ),
    (
        'Rango',
        0.000000,
        0.350000,
        'ISR',
        'Tasa',
        'No',
        'Sí',
        '2022-01-01',
        NULL
    );

-- INSERTS PARA catalogo_sat_tipo_factor
INSERT INTO
    catalogo_sat_tipo_factor (clave, fecha_inicio, fecha_fin)
VALUES
    ('Tasa', '2022-01-01', NULL),
    ('Cuota', '2022-01-01', NULL),
    ('Exento', '2022-01-01', NULL);

-- INSERTS PARA catalogo_sat_tipo_relacion
INSERT INTO
    catalogo_sat_tipo_relacion (clave, descripcion, fecha_inicio, fecha_fin)
VALUES
    (
        '01',
        'Nota de crédito de los documentos relacionados',
        '2022-01-01',
        NULL
    ),
    (
        '02',
        'Nota de débito de los documentos relacionados',
        '2022-01-01',
        NULL
    ),
    (
        '03',
        'Devolución de mercancía sobre facturas o traslados previos',
        '2022-01-01',
        NULL
    ),
    (
        '04',
        'Sustitución de los CFDI previos',
        '2022-01-01',
        NULL
    ),
    (
        '05',
        'Traslados de mercancías facturados previamente',
        '2022-01-01',
        NULL
    ),
    (
        '06',
        'Factura generada por los traslados previos',
        '2022-01-01',
        NULL
    ),
    (
        '07',
        'CFDI por aplicación de anticipo',
        '2022-01-01',
        NULL
    );

-- INSERTS PARA catalogo_sat_uso_cfdi
INSERT INTO
    catalogo_sat_uso_cfdi (
        clave,
        descripcion,
        aplica_fisica,
        aplica_moral,
        fecha_inicio,
        fecha_fin,
        regimenes
    )
VALUES
    (
        'G01',
        'Adquisición de mercancías.',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 606, 612, 620, 621, 622, 623, 624, 625,626'
    ),
    (
        'G02',
        'Devoluciones, descuentos o bonificaciones.',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 606, 612, 620, 621, 622, 623, 624, 625,626'
    ),
    (
        'G03',
        'Gastos en general.',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'
    ),
    (
        'I01',
        'Construcciones.',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'
    ),
    (
        'I02',
        'Mobiliario y equipo de oficina por inversiones.',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'
    ),
    (
        'I03',
        'Equipo de transporte.',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'
    ),
    (
        'I04',
        'Equipo de computo y accesorios.',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'
    ),
    (
        'I05',
        'Dados, troqueles, moldes, matrices y herramental.',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'
    ),
    (
        'I06',
        'Comunicaciones telefónicas.',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'
    ),
    (
        'I07',
        'Comunicaciones satelitales.',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'
    ),
    (
        'I08',
        'Otra maquinaria y equipo.',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'
    ),
    (
        'D01',
        'Honorarios médicos, dentales y gastos hospitalarios.',
        'Sí',
        'No',
        '2022-01-01',
        NULL,
        '605, 606, 608, 611, 612, 614, 607, 615, 625'
    ),
    (
        'D02',
        'Gastos médicos por incapacidad o discapacidad.',
        'Sí',
        'No',
        '2022-01-01',
        NULL,
        '605, 606, 608, 611, 612, 614, 607, 615, 625'
    ),
    (
        'D03',
        'Gastos funerales.',
        'Sí',
        'No',
        '2022-01-01',
        NULL,
        '605, 606, 608, 611, 612, 614, 607, 615, 625'
    ),
    (
        'D04',
        'Donativos.',
        'Sí',
        'No',
        '2022-01-01',
        NULL,
        '605, 606, 608, 611, 612, 614, 607, 615, 625'
    ),
    (
        'D05',
        'Intereses reales efectivamente pagados por créditos hipotecarios (casa habitación).',
        'Sí',
        'No',
        '2022-01-01',
        NULL,
        '605, 606, 608, 611, 612, 614, 607, 615, 625'
    ),
    (
        'D06',
        'Aportaciones voluntarias al SAR.',
        'Sí',
        'No',
        '2022-01-01',
        NULL,
        '605, 606, 608, 611, 612, 614, 607, 615, 625'
    ),
    (
        'D07',
        'Primas por seguros de gastos médicos.',
        'Sí',
        'No',
        '2022-01-01',
        NULL,
        '605, 606, 608, 611, 612, 614, 607, 615, 625'
    ),
    (
        'D08',
        'Gastos de transportación escolar obligatoria.',
        'Sí',
        'No',
        '2022-01-01',
        NULL,
        '605, 606, 608, 611, 612, 614, 607, 615, 625'
    ),
    (
        'D09',
        'Depósitos en cuentas para el ahorro, primas que tengan como base planes de pensiones.',
        'Sí',
        'No',
        '2022-01-01',
        NULL,
        '605, 606, 608, 611, 612, 614, 607, 615, 625'
    ),
    (
        'D10',
        'Pagos por servicios educativos (colegiaturas).',
        'Sí',
        'No',
        '2022-01-01',
        NULL,
        '605, 606, 608, 611, 612, 614, 607, 615, 625'
    ),
    (
        'S01',
        'Sin efectos fiscales.',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 605, 606, 608, 610, 611, 612, 614, 616, 620, 621, 622, 623, 624, 607, 615, 625, 626'
    ),
    (
        'CP01',
        'Pagos',
        'Sí',
        'Sí',
        '2022-01-01',
        NULL,
        '601, 603, 605, 606, 608, 610, 611, 612, 614, 616, 620, 621, 622, 623, 624, 607, 615, 625, 626'
    ),
    (
        'CN01',
        'Nómina',
        'Sí',
        'No',
        '2022-01-01',
        NULL,
        '605'
    );
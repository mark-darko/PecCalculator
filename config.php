<?php
    $config = [
        "apiUser" => "{your_api_login}",,
        "apiKeypec" => "{your_api_key}",

        "senderWarehouseId" => "b436c978-086d-11e6-b6ca-00155d668909",
        "plannedDateTime" => date('Y-m-d', strtotime('+1 day')) . 'T14:00:00',
        "deal_Id" => 4600,
        "transport_places" => 3,
        "total_volume_cm3" => 27000,
        "total_mass_g" => 9000,
        "payer_delivery" => "Покупатель",
        "shipment_to" => "1598",

        "company_Inn" => "7715633777",
        "company_Name" => "ООО Тестовая компания",
        "contact_phone" => "79161234567",
        "contact_last_name" => "Иванов",
        "contact_first_name" => "Иван",
        "contact_patronymic" => "Иванович",
        "opportunity" => 5000,
        "cargo_Name" => "Вентиляционное оборудование",
        "address" => "г. Лабинск, ул. Фрунзе, 2",
    ];

    extract($config, EXTR_SKIP);
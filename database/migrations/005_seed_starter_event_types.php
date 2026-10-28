<?php

declare(strict_types=1);

return new class {
    public function up(PDO $pdo): void
    {
        $starters = [
            [
                'name' => 'Vet visit',
                'description' => 'Check-ups, consultations, and clinic appointments.',
                'icon' => 'stethoscope',
                'color' => '#9BB8C9',
            ],
            [
                'name' => 'Vaccination',
                'description' => 'Immunizations and booster shots.',
                'icon' => 'syringe',
                'color' => '#A9BE9C',
            ],
            [
                'name' => 'Deworming',
                'description' => 'Internal parasite treatments.',
                'icon' => 'pill',
                'color' => '#E9AD78',
            ],
            [
                'name' => 'Flea and tick treatment',
                'description' => 'External parasite prevention and treatment.',
                'icon' => 'bug',
                'color' => '#B8A4C9',
            ],
            [
                'name' => 'Grooming',
                'description' => 'Brushing, coats, and professional grooming.',
                'icon' => 'scissors',
                'color' => '#D5A0A5',
            ],
            [
                'name' => 'Bath',
                'description' => 'Wash days and spa moments.',
                'icon' => 'droplets',
                'color' => '#8FBCB8',
            ],
            [
                'name' => 'Nail trimming',
                'description' => 'Keeping claws neat and comfortable.',
                'icon' => 'sparkles',
                'color' => '#E4BB93',
            ],
            [
                'name' => 'Medication',
                'description' => 'Prescriptions, doses, and ongoing treatments.',
                'icon' => 'heart-pulse',
                'color' => '#E2A096',
            ],
            [
                'name' => 'Weight check',
                'description' => 'Track weight over time.',
                'icon' => 'scale',
                'color' => '#CDB6A5',
            ],
        ];

        $exists = $pdo->prepare('SELECT id FROM event_types WHERE name = ? LIMIT 1');
        $insert = $pdo->prepare(
            'INSERT INTO event_types (
                name, description, icon, color,
                recurrence_interval, recurrence_unit, reminder_lead_days,
                created_at, updated_at
             ) VALUES (?, ?, ?, ?, NULL, NULL, 0, NOW(), NOW())'
        );

        foreach ($starters as $type) {
            $exists->execute([$type['name']]);
            if ($exists->fetch() !== false) {
                continue;
            }
            $insert->execute([
                $type['name'],
                $type['description'],
                $type['icon'],
                $type['color'],
            ]);
        }
    }
};

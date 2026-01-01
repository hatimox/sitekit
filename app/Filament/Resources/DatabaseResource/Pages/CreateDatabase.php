<?php

namespace App\Filament\Resources\DatabaseResource\Pages;

use App\Filament\Concerns\RequiresActiveServer;
use App\Filament\Resources\DatabaseResource;
use App\Models\DatabaseUser;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateDatabase extends CreateRecord
{
    use RequiresActiveServer;

    protected static string $resource = DatabaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = Filament::getTenant()->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        $database = $this->record;
        $formData = $this->form->getRawState();

        // Dispatch job to create database on server
        $payload = [
            'database_id' => $database->id,
            'database_name' => $database->name,
            'database_type' => $database->type,
            'host' => 'localhost',
        ];

        // Create user if requested
        if (!empty($formData['create_user']) && !empty($formData['db_username'])) {
            $payload['username'] = $formData['db_username'];
            $payload['password'] = $formData['db_password'];

            // Store user in database
            DatabaseUser::create([
                'database_id' => $database->id,
                'server_id' => $database->server_id,
                'username' => $formData['db_username'],
                'password' => $formData['db_password'],
            ]);
        }

        $database->dispatchJob('create_database', $payload);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}

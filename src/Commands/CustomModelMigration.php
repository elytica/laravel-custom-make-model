<?php
namespace Elytica\LaravelCustomMakeModel\Commands;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;

class CustomModelMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:custom-model {name} {fields*} {--controller} {--policy} {--request}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new model with predefined fillable fields and its migration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $fieldData = $this->argument('fields');
    
        $fields = [];
        foreach ($fieldData as $data) {
            $parts = explode(':', $data);
            $fieldName = $parts[0];
            $fieldType = $parts[1];
            $foreignReference = isset($parts[3]) ? $parts[3] : null;
            $referenceIdColumn = $parts[4] ?? 'id';
            $fields[$fieldName] = ['type' => $fieldType, 'foreign' => $foreignReference, 'reference_id' => $referenceIdColumn];
        }
    
        $modelContent = str_replace(
            ['DummyFillable', 'DummyClass'],
            [implode("', '", array_keys($fields)), $name],
            file_get_contents(base_path('stubs/model.custom.stub'))
        );
    
        file_put_contents(app_path("Models/{$name}.php"), $modelContent);
    
        $migrationName = "create_" . Str::snake(Str::plural($name)) . "_table";
        $migrationPath = database_path("migrations/" . date('Y_m_d_His') . "_{$migrationName}.php");
    
        $fieldsMigrations = collect($fields)->map(function ($details, $field) {
             return "\$table->{$details['type']}('{$field}');";
         })->toArray();

        // Add foreign key constraints
        $foreignConstraints = collect($fields)->filter(function ($details) {
            return $details['foreign'];
        })->map(function ($details, $field) {
            return "\$table->foreign('{$field}')->references('id')->on('{$details['foreign']}')->onDelete('cascade');";
        })->toArray();
        $spacing = PHP_EOL .'            ';
        $migrationFieldsContent = implode($spacing, $fieldsMigrations);
        $migrationFieldsContent .= $spacing . implode($spacing, $foreignConstraints);
    
        $foreignDropStatements = collect($fields)->filter(function ($details) {
            return $details['foreign'];
        })->map(function ($details, $field) {
            return "\$table->dropForeign(['{$field}']);";
        })->implode(PHP_EOL . '            ');
        
        $migrationContent = str_replace(
            ['DummyTable', 'DummyFields', 'DummyForeignKeys'],
            [Str::snake(Str::plural($name)), $migrationFieldsContent, $foreignDropStatements],
            file_get_contents(base_path('stubs/migration.custom.stub'))
        );
    
        file_put_contents($migrationPath, $migrationContent);
        $this->info('Model and migration created successfully!');
        if ($this->option('controller')) {
            $routeParamName = Str::snake($name);
            $controllerName = "{$name}Controller";
            $controllerStub = file_get_contents(base_path('stubs/controller.custom.stub'));
            $controllerContent = str_replace(
                ['DummyModel', 'DummyController', '${dummyModel}'],
                [$name, $controllerName, "\${$routeParamName}"],
                $controllerStub
            );
            $controllerPath = app_path("Http/Controllers/{$controllerName}.php");
            file_put_contents($controllerPath, $controllerContent);
            $this->info("{$controllerName} created successfully using custom stub!");
        }
        
        // Check if policy should be generated
        if ($this->option('policy')) {
            Artisan::call("make:policy {$name}Policy --model={$name}");
            $this->info("{$name}Policy created successfully!");
        }
        
        // Check if request should be generated
        if ($this->option('request')) {
            Artisan::call("make:request Store{$name}Request");
            Artisan::call("make:request Update{$name}Request");
            $typeToValidationRule = [
                'string' => 'string',
                'char' => 'string',
                'integer' => 'integer',
                'unsigned' => 'integer',
                'big' => 'integer',
                'medium' => 'integer',
                'tiny' => 'integer',
                'float' => 'numeric',
                'double' => 'numeric',
                'decimal' => 'numeric',
                'boolean' => 'boolean',
                'date' => 'date',
                'timestamp' => 'date_format:Y-m-d H:i:s'
            ];
            
            // Generate validation rules for Store{$name}Request
            $validationRules = collect($fields)->map(function ($details, $field) use ($typeToValidationRule) {
                $rule = $typeToValidationRule[$details['type']] ?? '';
                $foreign_exists = ($details['foreign'] ? "exists:$details['foreign'],$details['reference_id']" : '');
                $foreign_exists .= $foreign_exists ? "|{$foreign_exists}" : '';
                return "'$field' => 'required|{$rule}{$foreign_exists}'";
            })->implode(",\n            ");

            $requestFilePath = app_path("Http/Requests/Store{$name}Request.php");
            $requestContent = file_get_contents($requestFilePath);
            $requestContent = str_replace(
                '//',
                $validationRules,
                $requestContent
            );
            $authorizeLogic = "return \$this->user()->can('create', [App\Models\\{$name}::class]);";
            $requestContent = preg_replace('/return false;/', $authorizeLogic, $requestContent);
            file_put_contents($requestFilePath, $requestContent);

            // Generate validation rules for Update{$name}Request
            $updateValidationRules = collect($fields)->map(function ($details, $field) use ($typeToValidationRule) {
                $rule = $typeToValidationRule[$details['type']] ?? '';
                return "'$field' => '$rule'";
            })->implode(",\n            ");

            $updateRequestFilePath = app_path("Http/Requests/Update{$name}Request.php");
            $updateRequestContent = file_get_contents($updateRequestFilePath);
            $updateRequestContent = str_replace('//', $updateValidationRules, $updateRequestContent);
            $routeParamName = Str::snake($name);
            
            // Adjust the authorize method logic based on the policy and dynamic route parameter
            $authorizeUpdateLogic = <<<EOL
            \$modelInstance = \$this->route('{$routeParamName}');
            return \$this->user()->can('update', \$modelInstance);
            EOL;
            
            $updateRequestContent = preg_replace('/return false;/', $authorizeUpdateLogic, $updateRequestContent);
            file_put_contents($updateRequestFilePath, $updateRequestContent);

            $this->info("Store{$name}Request and Update{$name}Request created successfully!");
        }

    }

}

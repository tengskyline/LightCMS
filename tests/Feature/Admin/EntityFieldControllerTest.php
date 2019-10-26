<?php

namespace Tests\Feature\Admin;

use App\Model\Admin\AdminUser;
use App\Model\Admin\Entity;
use App\Model\Admin\EntityField;
use App\Repository\Admin\EntityRepository;
use App\Repository\Admin\ContentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EntityFieldControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $entity;
    protected $user;
    protected $filedName = 'title';

    public function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();

        $data = ['name' => '测试', 'table_name' => 'tests'];
        $this->entity = EntityRepository::add($data);
        $this->user = factory(AdminUser::class)->make(['id' => 1]);
    }

    public function testEntityFieldCanBeCreatedAndEdited()
    {
        $response = $this->createEntityField();
        $response->assertJson(['code' => 0]);
        $this->assertDatabaseHas(
            'entity_fields',
            [
                'entity_id' => $this->entity->id,
                'name' => 'title',
                'is_show' => EntityField::SHOW_DISABLE,
                'is_edit' => EntityField::EDIT_DISABLE,
                'is_required' => EntityField::REQUIRED_DISABLE,
                'is_show_inline' => EntityField::SHOW_NOT_INLINE,
            ]
        );
        $this->assertTrue(Schema::hasColumn($this->entity->table_name, $this->filedName));

        $data = [
            'entity_id' => $this->entity->id,
            'name' => $this->filedName,
            'type' => 'string',
            'form_name' => '修改标题',
            'form_type' => 'input',
            'order' => 77,
            'field_length' => '',
            'field_total' => '',
            'field_scale' => '',
            'comment' => '',
            'default_value' => '',
            'is_modify_db' => 1
        ];
        $response = $this->actingAs($this->user, 'admin')
            ->put('/admin/entityFields/1', $data);
        $response->assertJson(['code' => 0]);
        $this->assertDatabaseHas(
            'entity_fields',
            [
                'entity_id' => $this->entity->id,
                'name' => 'title',
                'form_name' => '修改标题'
            ]
        );
    }

    public function testEntityFieldCanBeCreatedWithNotModifyDB()
    {
        $response = $this->createEntityField(false);
        $response->assertJson(['code' => 0]);
        $this->assertDatabaseHas(
            'entity_fields',
            [
                'entity_id' => $this->entity->id,
                'name' => 'title',
                'is_show' => EntityField::SHOW_DISABLE,
                'is_edit' => EntityField::EDIT_DISABLE,
                'is_required' => EntityField::REQUIRED_DISABLE,
                'is_show_inline' => EntityField::SHOW_NOT_INLINE,
            ]
        );
        $this->assertFalse(Schema::hasColumn($this->entity->table_name, $this->filedName));
    }

    public function testEntityContentCanBeCreatedAndEditedAndListed()
    {
        // 字段可编辑
        $this->createEntityField(true, true);
        $data = [
            'title' => '测试标题',
            'tags' => '[{"value":"tag1"},{"value":"tag2"}]',
            'gender' => 1
        ];
        $response = $this->actingAs($this->user, 'admin')
            ->post('/admin/entity/' . $this->entity->id . '/contents', $data);
        $response->assertJson(['code' => 0]);

        $updateData = [
            'title' => '测试修改标题',
            'tags' => '[{"value":"tag1"},{"value":"tag3"}]',
            'gender' => 0
        ];
        $response = $this->actingAs($this->user, 'admin')
            ->put('/admin/entity/' . $this->entity->id . '/contents/1', $updateData);
        $response->assertJson(['code' => 0]);
        $this->assertEquals('tag1,tag3', ContentRepository::tagNames($this->entity->id, 1));
        $this->assertDatabaseHas($this->entity->table_name, ['title' => $updateData['title']]);

        $response = $this->actingAs($this->user, 'admin')
            ->get('/admin/entity/' . $this->entity->id . '/contents/list/?action=search&title=修改标题');
        $response->assertJson(['code' => 0]);
        $response->assertJsonFragment(['title' => $updateData['title']]);
    }

    public function testEntityContentCanNotBeEditedWhenFieldIsNotEditable()
    {
        // 字段不可编辑
        $this->createEntityField(true, false);
        $data = [
            'title' => '测试标题'
        ];
        $response = $this->actingAs($this->user, 'admin')
            ->post('/admin/entity/' . $this->entity->id . '/contents', $data);
        $response->assertJson(['code' => 0]);

        $updateData = [
            'title' => '测试修改标题'
        ];
        $response = $this->actingAs($this->user, 'admin')
            ->put('/admin/entity/' . $this->entity->id . '/contents/1', $updateData);
        $response->assertJson(['code' => 0]);
        $this->assertDatabaseMissing($this->entity->table_name, ['title' => $updateData['title']]);
    }

    protected function createEntityField($modifyDB = true, $is_edit = false)
    {
        $data = [
            'entity_id' => $this->entity->id,
            'name' => $this->filedName,
            'type' => 'string',
            'form_name' => '标题',
            'form_type' => 'input',
            'order' => 77,
            'field_length' => '',
            'field_total' => '',
            'field_scale' => '',
            'comment' => '',
            'default_value' => '',
            'is_edit' => $is_edit === true ? EntityField::EDIT_ENABLE : EntityField::EDIT_DISABLE,
        ];
        if ($modifyDB) {
            $data['is_modify_db'] = 1;
        }
        $this->actingAs($this->user, 'admin')
            ->post('/admin/entityFields', $data);

        // 标签字段
        $data = [
            'entity_id' => $this->entity->id,
            'name' => 'tags',
            'type' => 'string',
            'form_name' => '标签',
            'form_type' => 'inputTags',
            'order' => 77,
            'field_length' => '',
            'field_total' => '',
            'field_scale' => '',
            'comment' => '',
            'default_value' => '',
            'is_edit' => EntityField::EDIT_ENABLE,
            'is_modify_db' => 1,
        ];
        $this->actingAs($this->user, 'admin')
            ->post('/admin/entityFields', $data);

        // select类型字段
        $data = [
            'entity_id' => $this->entity->id,
            'name' => 'gender',
            'type' => 'integer',
            'form_name' => '性别',
            'form_type' => 'select',
            'order' => 77,
            'field_length' => '',
            'field_total' => '',
            'field_scale' => '',
            'comment' => '性别',
            'default_value' => '0',
            'form_params' => '0=男' . PHP_EOL . '1=女',
            'is_edit' => EntityField::EDIT_ENABLE,
            'is_modify_db' => 1,
        ];
        return $this->actingAs($this->user, 'admin')
            ->post('/admin/entityFields', $data);
    }

    public function tearDown(): void
    {
        Schema::dropIfExists('tests');
        Entity::query()->truncate();
        EntityField::query()->truncate();
        parent::tearDown();
    }
}

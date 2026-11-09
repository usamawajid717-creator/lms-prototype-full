<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\CourseReadingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseReadingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_can_create_publish_and_manage_a_course_reading_list(): void
    {
        $faculty = $this->createMember('Faculty');
        $student = $this->createMember('Student');
        $book = $this->createBook();

        $this->actingAs($faculty)->post(route('reading-lists.store'), [
            'course_code' => 'CS101',
            'course_name' => 'Introduction to Computing',
            'semester' => 'Fall',
            'academic_year' => 2026,
            'description' => 'Core reading for the course.',
        ])->assertRedirect();
        $list = CourseReadingList::query()->firstOrFail();
        $this->assertFalse($list->is_published);

        $this->actingAs($faculty)->get(route('reading-lists.show', $list))->assertOk()->assertSee('Introduction to Computing');
        $this->actingAs($student)->get(route('reading-lists.show', $list))->assertNotFound();

        $this->actingAs($faculty)->post(route('reading-lists.items.store', $list), [
            'book_id' => $book->id,
            'notes' => 'Read chapters one and two.',
        ])->assertRedirect(route('reading-lists.show', $list));
        $this->assertDatabaseHas('course_reading_list_items', [
            'course_reading_list_id' => $list->id,
            'book_id' => $book->id,
            'notes' => 'Read chapters one and two.',
        ]);
        $this->post(route('reading-lists.items.store', $list), ['book_id' => $book->id])
            ->assertSessionHasErrors('book_id');

        $this->put(route('reading-lists.update', $list), [
            'course_code' => 'CS101',
            'course_name' => 'Introduction to Computing',
            'semester' => 'Fall',
            'academic_year' => 2026,
            'is_published' => '1',
        ])->assertRedirect(route('reading-lists.show', $list));

        $this->actingAs($student)->get(route('reading-lists.index'))
            ->assertOk()
            ->assertSee('CS101');
        $this->get(route('reading-lists.show', $list))
            ->assertOk()
            ->assertSee('1 available now');

        $item = $list->items()->firstOrFail();
        $this->actingAs($faculty)->delete(route('reading-lists.items.destroy', [$list, $item]))
            ->assertRedirect(route('reading-lists.show', $list));
        $this->assertDatabaseMissing('course_reading_list_items', ['id' => $item->id]);
    }

    public function test_only_faculty_can_create_lists_and_owners_alone_can_manage_them(): void
    {
        $student = $this->createMember('Student');
        $faculty = $this->createMember('Faculty');
        $otherFaculty = $this->createMember('Faculty');
        $list = CourseReadingList::create([
            'user_id' => $faculty->id,
            'course_code' => 'BIO101',
            'course_name' => 'Biology',
            'semester' => 'Spring',
            'academic_year' => 2026,
            'is_published' => true,
        ]);

        $this->actingAs($student)->get(route('reading-lists.index'))->assertOk()->assertDontSee('Create a reading list');
        $this->post(route('reading-lists.store'), [
            'course_code' => 'X',
            'course_name' => 'Not allowed',
            'semester' => 'Fall',
            'academic_year' => 2026,
        ])->assertForbidden();
        $this->actingAs($otherFaculty)->put(route('reading-lists.update', $list), [
            'course_code' => 'BIO102',
            'course_name' => 'Changed',
            'semester' => 'Fall',
            'academic_year' => 2026,
        ])->assertForbidden();

        $this->actingAs($faculty)->delete(route('reading-lists.destroy', $list))->assertRedirect(route('reading-lists.index'));
        $this->assertDatabaseMissing('course_reading_lists', ['id' => $list->id]);
    }

    public function test_catalog_book_cannot_be_removed_while_assigned_to_a_reading_list(): void
    {
        $faculty = $this->createMember('Faculty');
        $admin = User::factory()->create(['role' => 'admin']);
        $book = $this->createBook();
        $list = CourseReadingList::create([
            'user_id' => $faculty->id,
            'course_code' => 'CS201',
            'course_name' => 'Data Structures',
            'semester' => 'Fall',
            'academic_year' => 2026,
        ]);
        $list->items()->create(['book_id' => $book->id]);

        $this->actingAs($admin)->delete(route('catalog.admin.destroy', $book))
            ->assertSessionHasErrors('book');

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    private function createMember(string $type): User
    {
        return User::factory()->create([
            'role' => 'member',
            'membership_type' => $type,
            'membership_status' => 'active',
            'membership_expires_at' => today()->addYear(),
        ]);
    }

    private function createBook(): Book
    {
        $category = BookCategory::create(['name' => 'Reading Lists', 'is_active' => true]);

        return Book::create([
            'isbn' => '9780000000501',
            'title' => 'Course Reading Book',
            'authors' => 'Course Author',
            'category_id' => $category->id,
            'total_copies' => 2,
            'available_copies' => 1,
        ]);
    }
}

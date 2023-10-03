import React from 'react';
import googleClassroom from '@/lib/config/googleClassroom';
import CourseItem from './CourseItem';

export default async function CoursesPage() {
  const classroom = await googleClassroom();
  const courses = await classroom.courses.list();

  return (
    <ul>
      {courses.data.courses?.map((course) => {
        return <CourseItem key={course.id} course={course} />;
      })}
    </ul>
  );
}

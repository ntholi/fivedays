import React from 'react';
import { classroom_v1 } from 'googleapis';
import googleClassroom from '@/lib/config/googleClassroom';

export default async function CoursesPage() {
  const classroom = await googleClassroom();

  const courses = await classroom.courses.list();

  return (
    <ul>
      {courses.data.courses?.map((course: classroom_v1.Schema$Course) => {
        return <li key={course.id}>{course.name}</li>;
      })}
    </ul>
  );
}

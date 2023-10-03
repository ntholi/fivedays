import React from 'react';
import googleClassroom from '@/lib/config/googleClassroom';
import CourseItem from './CourseItem';
import { Container, SimpleGrid } from '@mantine/core';

export default async function CoursesPage() {
  const classroom = await googleClassroom();
  const courses = await classroom.courses.list();

  return (
    <Container mt='lg' size='xl'>
      <SimpleGrid cols={{ base: 1, sm: 2, md: 3, xl: 4 }}>
        {courses.data.courses?.map((course) => (
          <CourseItem key={course.id} course={course} />
        ))}
      </SimpleGrid>
    </Container>
  );
}

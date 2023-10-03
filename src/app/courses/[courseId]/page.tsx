import React from 'react';
import googleClassroom from '@/lib/config/googleClassroom';
import { Button, Container, Group, Title } from '@mantine/core';
import { IconPlus } from '@tabler/icons-react';
import Body from './Body';

type Props = {
  params: {
    courseId: string;
  };
};

export async function getCourse(id: string) {
  const classroom = await googleClassroom();
  const { data: course } = await classroom.courses.get({ id });
  return course;
}

export async function generateMetadata({ params: { courseId } }: Props) {
  const course = await getCourse(courseId);
  return {
    title: course.name,
  };
}

export async function getCourseWorkList(courseId: string) {
  const classroom = await googleClassroom();
  const { data: courseWork } = await classroom.courses.courseWork.list({
    courseId,
  });
  return courseWork.courseWork;
}

export default async function CoursePage({ params: { courseId } }: Props) {
  const [course, courseWorkList] = await Promise.all([
    getCourse(courseId),
    getCourseWorkList(courseId),
  ]);

  return (
    <Container mt='lg' size='xl'>
      <Group justify='space-between'>
        <Title>{course.name}</Title>
        <Button variant='light' leftSection={<IconPlus size='1rem' />}>
          Create
        </Button>
      </Group>
      <Body course={course} courseWorkList={courseWorkList} />
    </Container>
  );
}

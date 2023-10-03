import React from 'react';
import googleClassroom from '@/lib/config/googleClassroom';
import { Accordion, Button, Container, Group, Title } from '@mantine/core';
import CourseWork from './CourseWork';
import { IconPlus } from '@tabler/icons-react';
import Body from './Body';

type Props = {
  params: {
    id: string;
  };
};

export async function getCourse(id: string) {
  const classroom = await googleClassroom();
  const { data: course } = await classroom.courses.get({ id });
  return course;
}

export async function generateMetadata({ params: { id } }: Props) {
  const course = await getCourse(id);
  return {
    title: course.name,
  };
}

export async function getCourseWorkList(id: string) {
  const classroom = await googleClassroom();
  const { data: courseWork } = await classroom.courses.courseWork.list({
    courseId: id,
  });
  return courseWork.courseWork;
}

export default async function CoursePage({ params: { id } }: Props) {
  const course = await getCourse(id);
  const courseWorkList = await getCourseWorkList(id);
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

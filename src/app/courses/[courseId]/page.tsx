import React from 'react';
import googleClassroom from '@/lib/config/googleClassroom';
import {
  Anchor,
  Breadcrumbs,
  Button,
  Container,
  Group,
  Title,
} from '@mantine/core';
import { IconPlus } from '@tabler/icons-react';
import Body from './Body';
import Link from 'next/link';

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

  const items = [{ title: 'Courses', href: '.' }].map((item, index) => (
    <Anchor component={Link} href={item.href} key={index}>
      {item.title}
    </Anchor>
  ));

  return (
    <Container mt='md' size='xl'>
      <Breadcrumbs>{items}</Breadcrumbs>
      <Group mt={'lg'} justify='space-between'>
        <Title>{course.name}</Title>
        <Button
          component={Link}
          href={`/courses/${courseId}/work/new`}
          variant='light'
          leftSection={<IconPlus size='1rem' />}
        >
          Create
        </Button>
      </Group>
      <Body course={course} courseWorkList={courseWorkList} />
    </Container>
  );
}

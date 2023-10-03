import googleClassroom from '@/lib/config/googleClassroom';
import { Container, Title } from '@mantine/core';
import React from 'react';

type Props = {
  params: {
    courseId: string;
    id: string;
  };
};

const getCourseWork = async (courseId: string, id: string) => {
  const classroom = await googleClassroom();
  const { data: courseWork } = await classroom.courses.courseWork.get({
    courseId,
    id,
  });
  return courseWork;
};

export async function generateMetadata({ params: { courseId, id } }: Props) {
  const courseWork = await getCourseWork(courseId, id);
  return {
    title: courseWork.title,
  };
}

export default async function CourseWorkPage({
  params: { id, courseId },
}: Props) {
  const courseWork = await getCourseWork(courseId, id);
  return (
    <Container mt='lg' size='xl'>
      <Title>{courseWork.title}</Title>
    </Container>
  );
}

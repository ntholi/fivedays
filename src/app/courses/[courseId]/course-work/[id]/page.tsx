import googleClassroom from '@/lib/config/googleClassroom';
import { formatDate } from '@/lib/utils/format';
import { Container, Title, Text, Divider } from '@mantine/core';
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
    <Container mt='lg' size='md'>
      <Title>{courseWork.title}</Title>
      <Text tt='capitalize'>
        {courseWork.workType?.toLowerCase()}
        <Text component={'span'} c='dimmed' size='sm'>
          &nbsp; ({courseWork.maxPoints} Points)
        </Text>
      </Text>
      <Text size='sm' c='dimmed'>
        Due Date: {formatDate(courseWork.dueDate, courseWork.dueTime)}
      </Text>

      <Divider my='md' />
      <Text>{courseWork.description}</Text>
    </Container>
  );
}

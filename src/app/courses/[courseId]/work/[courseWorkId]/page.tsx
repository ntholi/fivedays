import googleClassroom from '@/lib/config/googleClassroom';
import { formatDate } from '@/lib/utils/format';
import {
  Container,
  Title,
  Text,
  Divider,
  Flex,
  Button,
  Loader,
} from '@mantine/core';
import React, { Suspense } from 'react';
import SubmissionCount from './SubmissionCount';
import Link from 'next/link';

type Props = {
  params: {
    courseId: string;
    courseWorkId: string;
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

export async function generateMetadata({
  params: { courseId, courseWorkId },
}: Props) {
  const courseWork = await getCourseWork(courseId, courseWorkId);
  return {
    title: courseWork.title,
  };
}

export default async function CourseWorkPage({
  params: { courseWorkId, courseId },
}: Props) {
  const courseWork = await getCourseWork(courseId, courseWorkId);

  return (
    <Container mt='lg' size='md'>
      <Title>{courseWork.title}</Title>
      <div>
        <Text tt='capitalize'>
          {courseWork.workType?.toLowerCase()}
          <Text component={'span'} c='dimmed' size='sm'>
            &nbsp; ({courseWork.maxPoints} Points)
          </Text>
        </Text>
        <Text size='sm' c='dimmed'>
          Due: {formatDate(courseWork.dueDate, courseWork.dueTime)}
        </Text>
        <Text tt='capitalize' size='sm' c='dimmed'>
          {courseWork.state?.toLocaleLowerCase()}
        </Text>
      </div>
      <Flex mt='lg' justify={'space-between'} align='flex-end'>
        <Button
          component={Link}
          href={`/courses/${courseId}/work/${courseWorkId}/rubric`}
          variant='light'
        >
          Rubric
        </Button>
        <Button
          variant='light'
          rightSection={
            <Suspense fallback={<Loader color='blue' size='1.1rem' />}>
              <SubmissionCount
                courseId={courseId}
                courseWorkId={courseWorkId}
              />
            </Suspense>
          }
        >
          Student Work
        </Button>
      </Flex>
      <Divider my='md' />
      <Text>{courseWork.description}</Text>
    </Container>
  );
}

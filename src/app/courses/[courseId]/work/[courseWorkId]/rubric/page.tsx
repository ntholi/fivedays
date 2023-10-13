import React, { Suspense } from 'react';
import {
  ActionIcon,
  Button,
  Container,
  Divider,
  Flex,
  Skeleton,
} from '@mantine/core';
import RubricForm from './RubricForm';
import Heading from '../heading/Heading';
import googleClassroom from '@/lib/config/googleClassroom';
import { IconChevronLeft, IconWand } from '@tabler/icons-react';
import Link from 'next/link';
import RubricList from './RubricList';
import WandButton from '@/app/core/WandButton';

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

export default async function RubricPage({
  params: { courseId, courseWorkId },
}: Props) {
  const courseWork = await getCourseWork(courseId, courseWorkId);

  return (
    <Container mt='lg' size='md'>
      <Heading courseWork={courseWork} />
      <Flex mt='lg' justify={'space-between'} align='flex-end'>
        <Button
          variant='light'
          component={Link}
          href={'.'}
          leftSection={<IconChevronLeft />}
        >
          Back
        </Button>
        <WandButton />
      </Flex>
      <Divider my='md' />
      <RubricForm courseId={courseId} courseWorkId={courseWorkId} />
      <Suspense fallback={<Skeleton mt='xl' height={100} />}>
        <RubricList courseId={courseId} courseWorkId={courseWorkId} />
      </Suspense>
    </Container>
  );
}
